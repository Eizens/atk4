<?php
/**
 * Connects regular form with a Agile Data model and imports some fields.
 * It also binds action on form->update(), which will now force model to be updated.
 *
 * In most cases the following use is sufficient
 * $form->setModel('SomeModel');
 *
 * If you want to import fields from multiple models, you can use this:
 *  $ctl = $form->importFields($model, array('name', 'surname');
 *
 * and if you want to use your own class based on this one, syntax is:
 *  $ctl = $form->add('Controller_MVCForm_Derived')
 *              ->importFields($model, array('name', 'surname'));
 *
 * You can subsequently call importField() to add additional fields such as:
 *  $form_field = $ctl->importField('age');
 * which will return newly added form field.
 */
class Controller_ADForm extends AbstractController
{
    /** @var \atk4\data\Model */
    public $model = null;

    /** @var Form */
    public $form = null;

    /**
     * Field associations form_field => model_field
     *
     * @var array
     */
    public $field_associations = array();

    /**
     * Field type associations model_field_type => form_field_type
     *
     * @var array
     */
    public $type_associations = array(
        'string' => 'Line',
        'text' => 'Text',
        'int' => 'Number',
        'numeric' => 'Number',
        'money' => 'Money',
        'real' => 'Number',
        'date' => 'DatePicker',
        'datetime' => 'DatePicker',
        'daytime' => 'Time',
        'boolean' => 'Checkbox',
        'reference' => 'Readonly',
        'reference_id' => 'DropDown',
        'password' => 'Password',
        'list' => 'DropDown',
        'radio' => 'Radio',
        'readonly' => 'Readonly',
        'image' => 'Image',
        'file' => 'Upload',
    );

    /**
     * Is update hook already set?
     *
     * @var bool
     */
    private $_hook_set = false;

    /** @var Form */
    public $owner;



    /**
     * Initialization.
     */
    public function init()
    {
        parent::init();

        if (! $this->owner->model instanceof \atk4\data\Model) {
            throw $this->exception('Controller_ADForm can only be used with Agile Data \atk4\data\Model models');
        }
    }
    
    /**
     * Import model fields in form.
     *
     * @param array|string|bool $fields
     */
    public function setActualFields($fields)
    {
        /** @type \atk4\data\Model $this->owner->model */
        $this->importFields($this->owner->model, $fields);
    }


    /**
     * Import model fields in form.
     *
     * Use $fields === false if you want to associate form with model, but don't create form fields.
     *
     * @param \atk4\data\Model $model
     * @param array|string|bool $fields
     *
     * @return void|$this
     */
    public function importFields($model, $fields = UNDEFINED)
    {
        $this->model = $model;
        $this->form = $this->owner;

        if ($fields === false) {
            return;
        }

        if (!$fields || $fields === UNDEFINED) {
            if ($model->only_fields) {
                $fields = $model->only_fields;
            } else {
                $fields = [];
                // get all field-elements
                foreach ($model->elements as $field => $f_object) {
                    if ($f_object instanceof \atk4\data\Field) {
                        $fields[] = $field;
                    }
                }
            }
        }

        if (!is_array($fields)) {
            $fields = [$fields];
        }

        // import fields one by one
        foreach ($fields as $field) {
            $this->importField($field);
        }

        // set update hook
        if (!$this->_hook_set) {
            $this->owner->addHook('update', array($this, 'update'));
            $model->addHook('afterLoad', array($this, 'setFields'));
            $this->_hook_set = true;
        }

        return $this;
    }

    /**
     * Import one field from model into form.
     *
     * @param string $field
     * @param string $field_name
     *
     * @return void|Form_Field
     */
    public function importField($field, $field_name = null)
    {
        $field = $this->model->hasElement($field);
        if (!$field || !$field->editable /*|| $field->system*/) {
            return;
        }

        /** @type \atk4\data\Field $field */
        if ($field_name === null) {
            $field_name = $this->_unique($this->owner->elements, $field->short_name);
        }
        $field_type = $this->getFieldType($field);
        $field_caption = isset($field->caption) ? $field->caption : null;

        $this->field_associations[$field_name] = $field;

        // associate hasOne (Relation_One) fields with DropDown form field
        if ($ref_field = $this->model->hasElement('#ref_'.$field->short_name)) {
            $field_type = 'DropDown';
        }

        // associate enum fields with DropDown formfield
        if (isset($field->enum)) {
            $field_type = 'DropDown';
        }

        // add form field
        $form_field = $this->owner->addField($field_type, $field_name, $field_caption);
        $form_field->set($field->get());

        // set model for hasOne field
        if (isset($ref_field) && $ref_field) {
            $form_field->setModel($ref_field->getModel());
        }

        // set model for enum field
        if (isset($field->enum)) {
            $form_field->setValueList($field->enum);
        }

        // field value is mandatory
        if ($msg = $field->mandatory) {
            $form_field->validateNotNULL($msg);
        }

        // form field placeholder
        // not defined in Agile Data, but you can define this as custom model property
        $field_placeholder = isset($field->placeholder) ? $field->placeholder : /*$field->emptyText() ?:*/ null;
        if ($field_placeholder) {
            $form_field->setAttr('placeholder', $field_placeholder);
        }

        // form field hint
        // not defined in Agile Data, but you can define this as custom model property
        if (isset($field->hint) && $field->hint) {
            $form_field->setFieldHint($field->hint);
        }

        // set empty text option for dropdown type form fields if model field is not mandatory
        if ($form_field instanceof Form_Field_ValueList && !$field->mandatory) {
            /** @type string $text */
            //$text = $field->emptyText();
            //$form_field->setEmptyText($text);
            $form_field->setEmptyText('- no value -');
        }

        return $form_field;
    }


    /**
     * Copies model field values into form.
     */
    public function setFields()
    {
        foreach ($this->field_associations as $form_field => $model_field) {
            $this->form->set($form_field, $model_field->get());
        }
    }

    /**
     * Returns array of models model_name => \atk4\data\Model used in this form.
     *
     * @return array
     */
    public function getFields()
    {
        $models = array();

        /**
         * @var Form_Field $form_field
         * @var Field $model_field
         */
        foreach ($this->field_associations as $form_field => $model_field) {
            $v = $this->form->get($form_field);
            $m = $model_field->owner;
            $model_field->set($v);
            if (!isset($models[$m->name])) {
                $models[$m->name] = $m;
            }
        }

        return $models;
    }

    /**
     * Returns form field type associated with model field.
     *
     * Redefine this method to add special handling of your own fields.
     *
     * @param \atk4\data\Field $field
     *
     * @return string
     */
    public function getFieldType($field)
    {
        // default form field type
        $type = 'Line';

        // try to find associated form field type
        if (isset($this->type_associations[$field->type])) {
            $type = $this->type_associations[$field->type];
        }

        // if form field type explicitly set in model
        if (isset($field->display)) {
            $tmp = $field->display;
            if (is_array($tmp)) {
                $tmp = $tmp['form'];
            }
            if ($tmp) {
                $type = $tmp;
            }
        }

        return $type;
    }

    /**
     * Update form model
     *
     * @param Form $form
     */
    public function update($form)
    {
        $models = $this->getFields();
        foreach ($models as $model) {
            $model->save();
        }
    }
}