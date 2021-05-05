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

        $quotePaymentTypes = array(
            Quote_Models_Model_Quote::PAYMENT_TYPE_FULL => 'Full payment',
            Quote_Models_Model_Quote::PAYMENT_TYPE_FULL_SIGNATURE => 'Full payment + signature option',
            Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT => 'Partial payment',
            Quote_Models_Model_Quote::PAYMENT_TYPE_PARTIAL_PAYMENT_SIGNATURE => 'Partial payment + signature option',
            Quote_Models_Model_Quote::PAYMENT_TYPE_ONLY_SIGNATURE => 'Only signature required'
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

        $this->addElement(new Zend_Form_Element_Checkbox(array(
            'name'  => 'quoteDraggableProducts',
            'id'    => 'draggable-products',
            'label' => 'Enable products draggable'
        )));

        $this->addElement(new Zend_Form_Element_Checkbox(array(
            'name'  => 'enableQuoteDefaultType',
            'id'    => 'enable-quote-default-type',
            'label' => 'Enable quote payment type'
        )));

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'quotePaymentType',
            'id'           => 'quote-payment-types',
            'label'        => 'Quote payment types',
            'class'        => 'grid_6 alpha',
            'multiOptions' => $quotePaymentTypes
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'quotePartialPercentage',
            'id'    => 'quote-partial-percentage',
            'class' => 'grid_6 alpha',
            'label' => 'Partial payment percentage'
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'quoteDownloadLabel',
            'id'    => 'quote-download-label',
            'class' => 'grid_6 alpha',
            'label' => 'Quote download label',
            'placeholder' => 'Proposal-quote'
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
