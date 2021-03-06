<?php
/*****************************************************************************************
 * X2Engine Open Source Edition is a customer relationship management program developed by
 * X2Engine, Inc. Copyright (C) 2011-2015 X2Engine Inc.
 * 
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY X2ENGINE, X2ENGINE DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 * 
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE.  See the GNU Affero General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 * 
 * You can contact X2Engine, Inc. P.O. Box 66752, Scotts Valley,
 * California 95067, USA. or at email address contact@x2engine.com.
 * 
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 * 
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * X2Engine" logo. If the display of the logo is not reasonably feasible for
 * technical reasons, the Appropriate Legal Notices must display the words
 * "Powered by X2Engine".
 *****************************************************************************************/

Yii::import('application.components.WebFormDesigner.views.*');

/**
 * Parent Widget class to handle the 3 different Webforms
 */
class WebFormDesigner extends X2Widget {

    /**
     * Name of web form 
     * 'list', 'service', 'weblead'
     * @var string
     */
    public $type;

    

    /**
     * @var string
     */
    public $url;

    /**
     * @var string
     */
    public $saveUrl; 

    /**
     * Name of the JS Class
     * @var [type]
     */
    public $protoName;

    public $forms; 

    public $formAttrs = array();

    public $id;

    public $viewFile = 'application.components.WebFormDesigner.views._createWebForm2';

    /**
     * List of Default Fields
     * @var array
     */
    public $defaultList = array();

    

    public $modelName;

    public function init () {
        $this->JSClass = $this->protoName;

        

        foreach ($this->forms as $form) {
            $this->formAttrs[] = $form->attributes;
        }

    }

    public function run () {
        $this->registerPackages ();
        $this->instantiateJSClass ();
        $this->render($this->viewFile);
    }

    public function getPackages () {
        // Default Packages
        $this->_packages = array_merge ( parent::getPackages(), array(
                
                'WebFormDesignerJS' => array (
                    'baseUrl' => Yii::app()->baseUrl, 
                    'js' => array('js/WebFormDesigner/WebFormDesigner.js'),
                    'depends' => array('auxlib')
                ),
                'WebFormDesignerCSS' => array (
                    'baseUrl' => Yii::app()->theme->baseUrl, 
                    'css' => array ('css/createWebForm.css'), 
                ),
            )
        );

        return $this->_packages;
    }

    public function getJSObjectName () {
        return 'x2.webFormDesigner';
    }

    public function getJSClassParams () {
        $this->_JSClassParams = array_merge (parent::getJSClassParams(), array(
            'iframeSrc'               => Yii::app()->createExternalUrl($this->url),
            'externalAbsoluteBaseUrl' => Yii::app()->getExternalAbsoluteBaseUrl (),
            'saveUrl'                => Yii::app()->createAbsoluteUrl ($this->saveUrl),
            'savedForms'              => $this->formAttrs,
            'deleteFormUrl'           => Yii::app()->createAbsoluteUrl (
                                            '/marketing/marketing/deleteWebForm'),
            'fields'                  => array('fg','bgc','font','bs','bc'),
            'colorfields'             => array('fg','bgc','bc'),
        ));

        return $this->_JSClassParams;
    }

    /**
     * Builds the dropdown for saved webforms
     * @return html constructed HTML
     */
    public function getDropDown () {
        array_unshift($this->formAttrs, array('id'=>'0', 'name'=>'------------'));

        $html =  CHtml::dropDownList(
            'saved-forms', '',
            CHtml::encodeArray(CHtml::listData($this->formAttrs, 'id', 'name')),
            array (
                'class' => 'left'
            ));

        return $html;
    }

    /**
     * @see X2Widget::getTranslations
     */
    public function getTranslations () {
        return array (
            'formSavedMsg' => Yii::t('marketing', 'Form Saved'),
            'nameRequiredMsg' => Yii::t('marketing', 'Name cannot be blank.'),
            'Label:' => Yii::t('marketing', 'Label:'),
            'Value:' => Yii::t('marketing', 'Value:'),
        );
    }

    /**
     * Each web form has a unique view file that is rendered based on its type
     */
    public function renderSpecific() {
        $this->render('application.components.WebFormDesigner.views.'.$this->type);
    }
    
    public function getDescription() {
        return '';
    }

    


}

?>
