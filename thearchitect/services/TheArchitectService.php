<?php

namespace Craft;

class TheArchitectService extends BaseApplicationComponent
{
    private $groups;
    private $fields;
    private $sections;

    /**
     * parseJson.
     *
     * @param string $json
     *
     * @return array [successfulness]
     */
    public function parseJson($json)
    {
        $result = json_decode($json);

        $this->stripHandleSpaces($result);

        $notice = array();

        // Add Groups from JSON
        if (isset($result->groups)) {
            foreach ($result->groups as $group) {
                $addGroupResult = $this->addGroup($group);
                // Append Notice to Display Results
                $notice[] = array(
                    'type' => 'Group',
                    'name' => $group,
                    'result' => $addGroupResult,
                    'errors' => false,
                );
            }
        }

        $this->groups = craft()->fields->getAllGroups();

        // Add Fields from JSON
        if (isset($result->fields)) {
            foreach ($result->fields as $field) {
                $this->replaceSourcesHandles($field);
                $addFieldResult = $this->addField($field);
                // Append Notice to Display Results
                $notice[] = array(
                    'type' => 'Field',
                    'name' => $field->name,
                    'result' => $addFieldResult[0],
                    'errors' => $addFieldResult[1],
                    'errors_alt' => $addFieldResult[2],
                );
            }
        }

        $this->fields = craft()->fields->getAllFields();

        // Add Sections from JSON
        if (isset($result->sections)) {
            foreach ($result->sections as $section) {
                $addSectionResult = $this->addSection($section);
                // Append Notice to Display Results
                $notice[] = array(
                    'type' => 'Sections',
                    'name' => $section->name,
                    'result' => $addSectionResult[0],
                    'errors' => $addSectionResult[1],
                );
            }
        }

        $this->sections = craft()->sections->getAllSections();

        // Add Entry Types from JSON
        if (isset($result->entryTypes)) {
            foreach ($result->entryTypes as $entryType) {
                if (isset($entryType->titleLabel)) {
                    $entryTypeName = $entryType->titleLabel;
                } else {
                    $entryTypeName = $entryType->titleFormat;
                }
                $addEntryTypeResult = $this->addEntryType($entryType);
                // Append Notice to Display Results
                $notice[] = array(
                    'type' => 'Entry Types',
                    // Channels Might have an additional name.
                    'name' => $entryType->sectionHandle.((isset($entryType->name)) ? ' > '.$entryType->name : '').' > '.$entryTypeName,
                    'result' => $addEntryTypeResult[0],
                    'errors' => $addEntryTypeResult[1],
                );
            }
        }

        // Add Entry Types from JSON
        if (isset($result->sources)) {
            foreach ($result->sources as $source) {
                $assetSourceResult = $this->addAssetSource($source);
                // Append Notice to Display Results
                $notice[] = array(
                    'type' => 'Asset Source',
                    'name' => $source->name,
                    'result' => $assetSourceResult[0],
                    'errors' => $assetSourceResult[1],
                );
            }
        }

        // Add Entry Types from JSON
        if (isset($result->transforms)) {
            foreach ($result->transforms as $transform) {
                // Append Notice to Display Results
                $notice[] = array(
                    'type' => 'Asset Transform',
                    'name' => $transform->name,
                    'result' => $this->addAssetTransform($transform),
                    'errors' => false,
                );
            }
        }

        // Add Entry Types from JSON
        if (isset($result->globals)) {
            foreach ($result->globals as $global) {
                // Append Notice to Display Results
                $notice[] = array(
                    'type' => 'GlobalSet',
                    'name' => $global->name,
                    'result' => $this->addGlobalSet($global),
                    'errors' => false,
                );
            }
        }

        return $notice;
    }

    /**
     * exportConstruct.
     *
     * @param array $post
     *
     * @return array [output]
     */
    public function exportConstruct($post)
    {
        $transforms = [];
        $globals = [];

        list($sections, $entryTypes) = $this->sectionExport($post);
        list($groups, $fields) = $this->fieldExport($post);

        // Add all Arrays into the final output array
        $output = [
            'groups' => $groups,
            'sections' => $sections,
            'fields' => $fields,
            'entryTypes' => $entryTypes,
            'transforms' => $transforms,
            'globals' => $globals,
        ];

        // Remove empty sections from the output array
        foreach ($output as $key => $value) {
            if ($value == []) {
                unset($output[$key]);
            }
        }

        return $output;
    }

    /**
     * addGroup.
     *
     * @param string $name []
     *
     * @return bool [success]
     */
    public function addGroup($name)
    {
        $group = new FieldGroupModel();
        $group->name = $name;

        // Save Group to DB
        if (craft()->fields->saveGroup($group)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * addField.
     *
     * @param ArrayObject $jsonField
     *
     * @return bool [success]
     */
    public function addField($jsonField)
    {
        $field = new FieldModel();

        // If group is set find groupId
        if (isset($jsonField->group)) {
            $field->groupId = $this->getGroupId($jsonField->group);
        }

        $field->name = $jsonField->name;

        // Set handle if it was provided
        if (isset($jsonField->handle)) {
            $field->handle = $jsonField->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $field->handle = $this->constructHandle($jsonField->name);
        }

        // Set instructions if it was provided
        if (isset($jsonField->instructions)) {
            $field->instructions = $jsonField->instructions;
        }

        // Set translatable if it was provided
        if (isset($jsonField->translatable)) {
            $field->translatable = $jsonField->translatable;
        }

        $field->type = $jsonField->type;

        if (isset($jsonField->typesettings)) {
            // Convert Object to Array for saving
            $jsonField->typesettings = json_decode(json_encode($jsonField->typesettings), true);

            // $field->settings requires an array of the settings
            $field->settings = $jsonField->typesettings;
        }

        // Save Field to DB
        if (craft()->fields->saveField($field)) {
            return [true, false, false];
        } else {
            return [false, $field->getErrors(), $field->getSettingErrors()];
        }
    }

    /**
     * addSection.
     *
     * @param ArrayObject $jsonSection
     *
     * @return bool [success]
     */
    public function addSection($jsonSection)
    {
        $section = new SectionModel();

        $section->name = $jsonSection->name;

        // Set handle if it was provided
        if (isset($jsonSection->handle)) {
            $section->handle = $jsonSection->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $section->handle = $this->constructHandle($jsonSection->name);
        }

        $section->type = $jsonSection->type;

        // Set enableVersioning if it was provided
        if (isset($jsonSection->typesettings->enableVersioning)) {
            $section->enableVersioning = $jsonSection->typesettings->enableVersioning;
        } else {
            $section->enableVersioning = 1;
        }

        // Set hasUrls if it was provided
        if (isset($jsonSection->typesettings->hasUrls)) {
            $section->hasUrls = $jsonSection->typesettings->hasUrls;
        }

        // Set template if it was provided
        if (isset($jsonSection->typesettings->template)) {
            $section->template = $jsonSection->typesettings->template;
        }

        // Set maxLevels if it was provided
        if (isset($jsonSection->typesettings->maxLevels)) {
            $section->maxLevels = $jsonSection->typesettings->maxLevels;
        }

        // Set Locale Information
        $locales = [];

        $allLocales = craft()->i18n->getAllLocales();
        foreach ($allLocales as $locale) {
            $localeId = $locale->id;
            if (isset($jsonSection->typesettings->$localeId->urlFormat)) {
                $urlFormat = $jsonSection->typesettings->$localeId->urlFormat;
            } else {
                $urlFormat = null;
            }

            if (isset($jsonSection->typesettings->$localeId->nestedUrlFormat)) {
                $nestedUrlFormat = $jsonSection->typesettings->$localeId->nestedUrlFormat;
            } else {
                $nestedUrlFormat = null;
            }

            if (isset($jsonSection->typesettings->$localeId->defaultLocaleStatus)) {
                $defaultLocaleStatus = $jsonSection->typesettings->$localeId->defaultLocaleStatus;
            } else {
                $defaultLocaleStatus = true;
            }

            if ($urlFormat !== null || $nestedUrlFormat !== null) {
                $locales[$localeId] = new SectionLocaleModel(array(
                    'locale' => $localeId,
                    'enabledByDefault' => $defaultLocaleStatus,
                    'urlFormat' => $urlFormat,
                    'nestedUrlFormat' => $nestedUrlFormat,
                ));
            }
        }
        // Pulled from SectionController.php aprox. Ln 170
        $primaryLocaleId = craft()->i18n->getPrimarySiteLocaleId();
        $localeIds = array($primaryLocaleId);
        foreach ($localeIds as $localeId) {
            if (isset($jsonSection->typesettings->urlFormat)) {
                $urlFormat = $jsonSection->typesettings->urlFormat;
            } else {
                $urlFormat = null;
            }

            if (isset($jsonSection->typesettings->nestedUrlFormat)) {
                $nestedUrlFormat = $jsonSection->typesettings->nestedUrlFormat;
            } else {
                $nestedUrlFormat = null;
            }

            if (isset($jsonSection->typesettings->defaultLocaleStatus)) {
                $defaultLocaleStatus = $jsonSection->typesettings->defaultLocaleStatus;
            } else {
                $defaultLocaleStatus = true;
            }


            if ($urlFormat !== null || $nestedUrlFormat !== null) {
                $locales[$localeId] = new SectionLocaleModel(array(
                    'locale' => $localeId,
                    'enabledByDefault' => $defaultLocaleStatus,
                    'urlFormat' => $urlFormat,
                    'nestedUrlFormat' => $nestedUrlFormat,
                ));
            }
        }
        $section->setLocales($locales);

        // Save Section to DB
        if (craft()->sections->saveSection($section)) {
            return [true, false];
        } else {
            return [false, $section->getErrors()];
        }
    }

    /**
     * addEntryType.
     *
     * @param ArrayObject $jsonEntryType
     *
     * @return bool [success]
     */
    public function addEntryType($jsonEntryType)
    {
        $entryType = new EntryTypeModel();

        // Set handle if it was provided
        if (isset($jsonEntryType->handle)) {
            $entryType->handle = $jsonEntryType->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $entryType->handle = $this->constructHandle($jsonEntryType->name);
        }

        $entryType->sectionId = $this->getSectionId($jsonEntryType->sectionHandle);

        // If the Entry Type exists load it so that it udpates it.
        $sectionHandle = $this->getSectionHandle($entryType->sectionId);
        $entryTypes = craft()->sections->getEntryTypesByHandle($entryType->handle);
        if ($entryTypes) {
            $entryType = craft()->sections->getEntryTypeById($entryTypes[0]->attributes['id']);
        }

        $entryType->name = $jsonEntryType->name;

        // If titleLabel set hasTitleField to True
        if (isset($jsonEntryType->titleLabel)) {
            $entryType->hasTitleField = true;
            $entryType->titleLabel = $jsonEntryType->titleLabel;
        }
        // If titleFormat set hasTitleField to False
        else {
            $entryType->hasTitleField = false;
            $entryType->titleFormat = $jsonEntryType->titleFormat;
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonEntryType->fieldLayout)) {
            $requiredFields = [];
            if (isset($jsonEntryType->requiredFields)) {
                foreach ($jsonEntryType->requiredFields as $requirdField) {
                    array_push($requiredFields, $this->getFieldId($requirdField));
                }
            }
            $fieldLayout = $this->assembleLayout($jsonEntryType->fieldLayout, $requiredFields);
            $fieldLayout->type = ElementType::Entry;
            $entryType->setFieldLayout($fieldLayout);
        }

        // Save Entry Type to DB
        if (craft()->sections->saveEntryType($entryType)) {
            return [true, false];
        } else {
            return [false, $entryType->getErrors()];
        }
    }

    /**
     * addAssetSource.
     *
     * @param ArrayObject $jsonSection
     *
     * @return bool [success]
     */
    public function addAssetSource($jsonSource)
    {
        $source = new AssetSourceModel();

        $source->name = $jsonSource->name;

        // Set handle if it was provided
        if (isset($jsonSource->handle)) {
            $source->handle = $jsonSource->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $source->handle = $this->constructHandle($jsonSource->name);
        }

        $source->type = $jsonSource->type;

        // Convert Object to Array for saving
        $source->settings = json_decode(json_encode($jsonSource->settings), true);

        // Parse & Set Field Layout if Provided
        if (isset($jsonSource->fieldLayout)) {
            $requiredFields = [];
            if (isset($jsonSource->requiredFields)) {
                foreach ($jsonSource->requiredFields as $requirdField) {
                    array_push($requiredFields, $this->getFieldId($requirdField));
                }
            }
            $fieldLayout = $this->assembleLayout($jsonSource->fieldLayout, $requiredFields);
            $fieldLayout->type = ElementType::Asset;
            $source->setFieldLayout($fieldLayout);
        }

        // Save Asset Source to DB
        if (craft()->assetSources->saveSource($source)) {
            return [true, null];
        } else {
            return [false, $source->getErrors()];
        }
    }

    /**
     * addAssetTransform.
     *
     * @param ArrayObject $jsonAssetTransform
     *
     * @return bool [success]
     */
    public function addAssetTransform($jsonAssetTransform)
    {
        $transform = new AssetTransformModel();

        $transform->name = $jsonAssetTransform->name;

        // Set handle if it was provided
        if (isset($jsonAssetTransform->handle)) {
            $transform->handle = $jsonAssetTransform->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $transform->handle = $this->constructHandle($jsonAssetTransform->name);
        }

        // One of these fields are required.
        if (isset($jsonAssetTransform->width) or isset($jsonAssetTransform->height)) {
            if (isset($jsonAssetTransform->width)) {
                $transform->width = $jsonAssetTransform->width;
            }
            if (isset($jsonAssetTransform->height)) {
                $transform->height = $jsonAssetTransform->height;
            }
        } else {
            return false;
        }

        // Set mode if it was provided
        if (isset($jsonAssetTransform->mode)) {
            $transform->mode = $jsonAssetTransform->mode;
        }

        // Set position if it was provided
        if (isset($jsonAssetTransform->position)) {
            $transform->position = $jsonAssetTransform->position;
        }

        // Set quality if it was provided
        if (isset($jsonAssetTransform->quality)) {
            // Quality must be greater than 0
            if ($jsonAssetTransform->quality < 1) {
                $transform->quality = 1;
            }
            // Quality must not be greater than 100
            elseif ($jsonAssetTransform->quality > 100) {
                $transform->quality = 100;
            } else {
                $transform->quality = $jsonAssetTransform->quality;
            }
        }

        // Set format if it was provided
        if (isset($jsonAssetTransform->format)) {
            $transform->format = $jsonAssetTransform->format;
        }
        // If not provided set to Auto format
        else {
            $transform->format = null;
        }

        // Save Asset Source to DB
        if (craft()->assetTransforms->saveTransform($transform)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * addGlobalSet.
     *
     * @param ArrayObject $jsonGlobalSet
     *
     * @return bool [success]
     */
    public function addGlobalSet($jsonGlobalSet)
    {
        $globalSet = new GlobalSetModel();

        $globalSet->name = $jsonGlobalSet->name;

        // Set handle if it was provided
        if (isset($jsonGlobalSet->handle)) {
            $globalSet->handle = $jsonGlobalSet->handle;
        }
        // Construct handle if one wasn't provided
        else {
            $globalSet->handle = $this->constructHandle($jsonGlobalSet->name);
        }

        // Parse & Set Field Layout if Provided
        if (isset($jsonGlobalSet->fieldLayout)) {
            $requiredFields = [];
            if (isset($jsonSource->requiredFields)) {
                foreach ($jsonSource->requiredFields as $requirdField) {
                    array_push($requiredFields, $this->getFieldId($requirdField));
                }
            }
            $fieldLayout = $this->assembleLayout($jsonGlobalSet->fieldLayout, $requiredFields);
            $fieldLayout->type = ElementType::GlobalSet;
            $globalSet->setFieldLayout($fieldLayout);
        }

        // Save Asset Source to DB
        if (craft()->globals->saveSet($globalSet)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * getGroupId.
     *
     * @param string $name [name to find ID for]
     *
     * @return int
     */
    public function getGroupId($name)
    {
        $firstId = $this->groups[0]['id'];
        foreach ($this->groups as $group) {
            if ($group->attributes['name'] == $name) {
                return $group->attributes['id'];
            }
        }

        return $firstId;
    }

    /**
     * getFieldId.
     *
     * @param string $name [name to find ID for]
     *
     * @return int
     */
    public function getFieldId($name)
    {
        foreach ($this->fields as $field) {
            // Return ID if handle matches the search
            if ($field->attributes['handle'] == $name) {
                return $field->attributes['id'];
            }

            // Return ID if name matches the search
            if ($field->attributes['name'] == $name) {
                return $field->attributes['id'];
            }
        }

        return false;
    }

    /**
     * getSectionId.
     *
     * @param string $name [name to find ID for]
     *
     * @return int
     */
    public function getSectionId($name)
    {
        foreach ($this->sections as $section) {
            // Return ID if handle matches the search
            if ($section->attributes['handle'] == $name) {
                return $section->attributes['id'];
            }

            // Return ID if name matches the search
            if ($section->attributes['name'] == $name) {
                return $section->attributes['id'];
            }
        }

        return false;
    }

    /**
     * getSectionHandle.
     *
     * @param int $id [ID to find handle for]
     *
     * @return string
     */
    public function getSectionHandle($id)
    {
        foreach ($this->sections as $section) {
            // Return ID if handle matches the search
            if ($section->attributes['id'] == $id) {
                return $section->attributes['handle'];
            }
        }

        return false;
    }

    /**
     * constructHandle.
     *
     * @param string $str [input string]
     *
     * @return string [the constructed handle]
     */
    public function constructHandle($string)
    {
        $string = strtolower($string);

        $words = explode(' ', $string);

        for ($i = 1; $i < count($words); ++$i) {
            $words[$i] = ucfirst($words[$i]);
        }

        return implode('', $words);
    }

    // Private Functions
    // =========================================================================

    /**
     * assembleLayout.
     *
     * @param array $fieldLayout
     * @param array $requiredFields
     *
     * @return FieldLayoutModel
     */
    private function assembleLayout($fieldLayout, $requiredFields = array())
    {
        $fieldLayoutPost = array();

        foreach ($fieldLayout as $tab => $fields) {
            $fieldLayoutPost[$tab] = array();
            foreach ($fields as $field) {
                $fieldLayoutPost[$tab][] = $this->getFieldId($field);
            }
        }

        return craft()->fields->assembleLayout($fieldLayoutPost, $requiredFields);
    }

    private function getSourceByHandle($handle)
    {
        $assetSources = craft()->assetSources->getAllSources();
        foreach ($assetSources as $key => $assetSource) {
            if ($assetSource->handle === $handle) {
                return $assetSource;
            }
        }
    }

    private function getCategoryByHandle($handle)
    {
        $categories = craft()->categories->getAllGroups();
        foreach ($categories as $key => $category) {
            if ($category->handle === $handle) {
                return $category;
            }
        }
    }

    private function getTagGroupByHandle($handle)
    {
        $tagGroups = craft()->tags->getAllTagGroups();
        foreach ($tagGroups as $key => $tagGroup) {
            if ($tagGroup->handle === $handle) {
                return $tagGroup;
            }
        }
    }

    private function getUserGroupByHandle($handle)
    {
        $userGroups = craft()->userGroups->getAllGroups();
        foreach ($userGroups as $key => $userGroup) {
            if ($userGroup->handle === $handle) {
                return $userGroup;
            }
        }
    }

    private function stripHandleSpaces(&$object)
    {
        foreach ($object as $key => &$value) {
            if ($key === "handle") {
                $value = preg_replace("/\s/", '', $value);
            }
            if (gettype($value) == 'array' || gettype($value) == 'object') {
                $value = $this->stripHandleSpaces($value);
            }
        }
        return $object;
    }

    private function replaceSourcesHandles(&$object)
    {
        if ($object->type == 'Matrix') {
            if (isset($object->typesettings->blockTypes)) {
                foreach ($object->typesettings->blockTypes as &$blockType) {
                    foreach ($blockType->fields as &$field) {
                        $this->replaceSourcesHandles($field);
                    }
                }
            }
        }
        if ($object->type == 'Neo') {
            if (isset($object->typesettings->blockTypes)) {
                foreach ($object->typesettings->blockTypes as &$blockType) {
                    foreach ($blockType->fieldLayout as &$fieldLayout) {
                        foreach ($fieldLayout as &$fieldHandle) {
                            $field = craft()->fields->getFieldByHandle($fieldHandle);
                            if ($field !== null) {
                                $fieldHandle = $field->id;
                            }
                        }
                    }
                }
            }
        }
        if ($object->type == 'SuperTable') {
            if (isset($object->typesettings->blockTypes)) {
                foreach ($object->typesettings->blockTypes as &$blockType) {
                    foreach ($blockType->fields as &$field) {
                        $this->replaceSourcesHandles($field);
                    }
                }
            }
        }
        if ($object->type == 'Entries') {
            if (isset($object->typesettings->sources)) {
                foreach ($object->typesettings->sources as $k => &$v) {
                    $section = craft()->sections->getSectionByHandle($v);
                    if ($section) {
                        $v = 'section:'.$section->id;
                    }
                }
            }
        }
        if ($object->type == 'Assets') {
            if (isset($object->typesettings->sources)) {
                foreach ($object->typesettings->sources as $k => &$v) {
                    $assetSource = $this->getSourceByHandle($v);
                    if ($assetSource) {
                        $v = 'folder:'.$assetSource->id;
                    }
                }
            }
        }
        if ($object->type == 'Categories') {
            if (isset($object->typesettings->source)) {
                $category = $this->getCategoryByHandle($object->typesettings->source);
                if ($category) {
                    $object->typesettings->source = 'group:'.$category->id;
                }
            }
        }
        if ($object->type == 'Tags') {
            if (isset($object->typesettings->source)) {
                $category = $this->getTagGroupByHandle($object->typesettings->source);
                if ($category) {
                    $object->typesettings->source = 'taggroup:'.$category->id;
                }
            }
        }
        if ($object->type == 'Users') {
            if (isset($object->typesettings->sources)) {
                foreach ($object->typesettings->sources as $k => &$v) {
                    $userGroup = $this->getUserGroupByHandle($v);
                    if ($userGroup) {
                        $v = 'group:'.$userGroup->id;
                    }
                }
            }
        }
    }

    private function sectionExport($post)
    {
        $sections = [];
        $entryTypes = [];
        if (isset($post['sectionSelection'])) {
            foreach ($post['sectionSelection'] as $id) {
                $section = craft()->sections->getSectionById($id);
                if ($section === null) {
                    continue;
                }

                $newSection = [
                    'name' => $section->attributes['name'],
                    'handle' => $section->attributes['handle'],
                    'type' => $section->attributes['type'],
                    'enableVersioning' => $section->attributes['enableVersioning'],
                    'typesettings' => [
                        'hasUrls' => $section->attributes['hasUrls'],
                        'urlFormat' => $section->getUrlFormat(),
                        'nestedUrlFormat' => $section->locales[craft()->i18n->getPrimarySiteLocaleId()]->attributes['nestedUrlFormat'],
                        'template' => $section->attributes['template'],
                        'maxLevels' => $section->attributes['maxLevels'],
                    ],
                ];
                if ($newSection['typesettings']['maxLevels'] === null) {
                    unset($newSection['typesettings']['maxLevels']);
                }
                if ($newSection['typesettings']['nestedUrlFormat'] === null) {
                    unset($newSection['typesettings']['nestedUrlFormat']);
                }
                if ($newSection['type'] === 'single') {
                    unset($newSection['typesettings']['hasUrls']);
                }
                // Add New Section into the Sections Array
                array_push($sections, $newSection);

                $sectionEntryTypes = $section->getEntryTypes();
                foreach ($sectionEntryTypes as $entryType) {
                    $newEntryType = [
                        'sectionHandle' => $section->attributes['handle'],
                        'hasTitleField' => $entryType->attributes['hasTitleField'],
                        'titleLabel' => $entryType->attributes['titleLabel'],
                        'titleFormat' => $entryType->attributes['titleFormat'],
                        'name' => $entryType->attributes['name'],
                        'handle' => $entryType->attributes['handle'],
                        'titleLabel' => $entryType->attributes['titleLabel'],
                        'fieldLayout' => [],
                    ];
                    if ($newEntryType['titleFormat'] === null) {
                        unset($newEntryType['titleFormat']);
                    }
                    foreach ($entryType->getFieldLayout()->getTabs() as $tab) {
                        $newEntryType['fieldLayout'][$tab->name] = [];
                        foreach ($tab->getFields() as $tabField) {
                            array_push($newEntryType['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                        }
                    }
                    // Add New EntryType into the EntryTypes Array
                    array_push($entryTypes, $newEntryType);
                }
            }
        }

        return [$sections, $entryTypes];
    }

    private function fieldExport($post)
    {
        $groups = [];
        $fields = [];
        $fieldsLast = [];
        if (isset($post['fieldSelection'])) {
            foreach ($post['fieldSelection'] as $id) {
                $field = craft()->fields->getFieldById($id);
                if ($field === null) {
                    continue;
                }

                // If Field Group is not defined in Groups Array add it
                if (!in_array($field->group->name, $groups)) {
                    array_push($groups, $field->group->name);
                }

                $newField = [
                    'group' => $field->group->name,
                    'name' => $field->name,
                    'handle' => $field->handle,
                    'instructions' => $field->instructions,
                    'required' => $field->required,
                    'type' => $field->type,
                    'typesettings' => $field->settings,
                ];

                if ($field->type == 'Assets') {
                    if ($newField['typesettings']['sources']) {
                        foreach ($newField['typesettings']['sources'] as $key => $value) {
                            if (substr($value, 0, 7) == 'folder:') {
                                $source = craft()->assetSources->getSourceById(intval(substr($value, 7)));
                                $newField['typesettings']['sources'][$key] = $source->handle;
                            }
                        }
                    }
                }

                if ($field->type == 'Categories') {
                    if ($newField['typesettings']['source']) {
                        if (substr($newField['typesettings']['source'], 0, 6) == 'group:') {
                            $category = craft()->categories->getGroupById(intval(substr($newField['typesettings']['source'], 6)));
                            $newField['typesettings']['source'] = $category->handle;
                        }
                    }
                }

                if ($field->type == 'Entries') {
                    if ($newField['typesettings']['sources']) {
                        foreach ($newField['typesettings']['sources'] as $key => $value) {
                            if (substr($value, 0, 8) == 'section:') {
                                $source = craft()->sections->getSectionById(intval(substr($value, 8)));
                                $newField['typesettings']['sources'][$key] = $source->handle;
                            }
                        }
                    }
                }

                if ($field->type == 'Tags') {
                    if ($newField['typesettings']['source']) {
                        if (substr($newField['typesettings']['source'], 0, 9) == 'taggroup:') {
                            $category = craft()->tags->getTagGroupById(intval(substr($newField['typesettings']['source'], 9)));
                            $newField['typesettings']['source'] = $category->handle;
                        }
                    }
                }

                if ($field->type == 'Users') {
                    if ($newField['typesettings']['sources']) {
                        foreach ($newField['typesettings']['sources'] as $key => $value) {
                            if (substr($value, 0, 6) == 'group:') {
                                $source = craft()->userGroups->getGroupById(intval(substr($value, 6)));
                                $newField['typesettings']['sources'][$key] = $source->handle;
                            }
                        }
                    }
                }

                if ($field->type == 'Neo') {
                    $blockTypes = craft()->neo->getBlockTypesByFieldId($id);
                    $blockCount = 0;
                    foreach ($blockTypes as $blockType) {
                        $newField['typesettings']['blockTypes']['new'.$blockCount] = [
                            'sortOrder' => $blockType->sortOrder,
                            'name' => $blockType->name,
                            'handle' => $blockType->handle,
                            'maxBlocks' => $blockType->maxBlocks,
                            'childBlocks' => $blockType->childBlocks,
                            'topLevel' => $blockType->topLevel,
                            'fieldLayout' => [],
                        ];
                        foreach ($blockType->getFieldLayout()->getTabs() as $tab) {
                            $newField['typesettings']['blockTypes']['new'.$blockCount]['fieldLayout'][$tab->name] = [];
                            foreach ($tab->getFields() as $tabField) {
                                array_push($newField['typesettings']['blockTypes']['new'.$blockCount]['fieldLayout'][$tab->name], craft()->fields->getFieldById($tabField->fieldId)->handle);
                            }
                        }
                        ++$blockCount;
                    }
                }

                if ($field->type == 'Matrix') {
                    $blockTypes = craft()->matrix->getBlockTypesByFieldId($id);
                    $blockCount = 1;
                    foreach ($blockTypes as $blockType) {
                        $newField['typesettings']['blockTypes']['new'.$blockCount] = [
                            'name' => $blockType->name,
                            'handle' => $blockType->handle,
                            'fields' => [],
                        ];
                        $fieldCount = 1;
                        foreach ($blockType->fields as $blockField) {
                            $newField['typesettings']['blockTypes']['new'.$blockCount]['fields']['new'.$fieldCount] = [
                                'name' => $blockField->name,
                                'handle' => $blockField->handle,
                                'instructions' => $blockField->instructions,
                                'required' => $blockField->required,
                                'type' => $blockField->type,
                                'typesettings' => $blockField->settings,
                            ];
                            ++$fieldCount;
                        }
                        ++$blockCount;
                    }
                }

                // If Field Type is Neo store it for pushing last. This is needed because Neo fields reference other fields.
                if ($field->type == 'Neo') {
                    array_push($fieldsLast, $newField);
                }
                // Add New Field into the Fields Array
                else {
                    array_push($fields, $newField);
                }
            }
        }
        // Push fields that need to be at the end of fields array
        foreach ($fieldsLast as $newField) {
            array_push($fields, $newField);
        }

        return [$groups, $fields];
    }
}
