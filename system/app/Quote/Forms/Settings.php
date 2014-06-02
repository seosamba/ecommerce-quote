<?php
class  Quote_Forms_Settings extends Zend_Form {

	public function init() {

		$this->setAttribs(array(
            'id'     => 'quote-settings-info',
            'class'  => 'quote-settings _fajax',
            'method' => Zend_Form::METHOD_POST,
            'action' => $this->getView()->websiteUrl . 'plugin/quote/run/settings/'
		));

        $this->setDecorators(array('FormElements', 'Form'));

		$this->addElement(new Zend_Form_Element_Checkbox(array(
			'name'  => 'autoQuote',
			'id'    => 'auto-quote',
			'label' => 'Generate quote automatically'
		)));

        $quoteTemplateOptions = array_merge(
            array(0 => 'Select quote template'),
            Tools_System_Tools::getTemplatesHash(Quote_Models_Model_Quote::TEMPLATE_TYPE_QUOTE)
        );

		$this->addElement(new Zend_Form_Element_Select(array(
			'name'         => 'quoteTemplate',
			'id'           => 'quote-template',
			'label'        => 'Quote template',
			'class'        => 'grid_6 alpha',
			'multiOptions' => $quoteTemplateOptions
		)));

        //default quote expiration delay
        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'expirationDelay',
            'id'    => 'expiration-delay',
            'class' => 'grid_6 alpha',
            'label' => 'Default quote expiration delay'
        )));

        $this->setDecorators(array('FormElements', 'Form'))
			->setElementDecorators(array(
				'ViewHelper',
				array('Label', array('class' => 'grid_6')),
				array('HtmlTag', array('tag' => 'p'))
			));

        $this->addElement(new Zend_Form_Element_Button(array(
            'name'       => 'applySettings',
            'label'      => 'Update quote configuration',
            'type'       => 'submit',
            'class'      => 'btn block',
            'ignore'     => true,
	        'decorators' => array(
		        'ViewHelper',
                array('HtmlTag', array('tag' => 'p', 'class' => 'grid_12' ))
            )
        )));
	}
}
