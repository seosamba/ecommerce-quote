<?php
class  Quote_Forms_Settings extends Zend_Form {

	public function init() {

        $translator = Zend_Registry::get('Zend_Translate');

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
			'label' => $translator->translate('Generate quote automatically')
		)));

        $quoteTemplateOptions = array_merge(
            array(0 => $translator->translate('Select quote template')),
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
			'label'        => $translator->translate('Quote template'),
			'class'        => 'grid_6 alpha',
			'multiOptions' => $quoteTemplateOptions
		)));

        //default quote expiration delay
        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'expirationDelay',
            'id'    => 'expiration-delay',
            'class' => 'grid_6 alpha',
            'label' => $translator->translate('Default quote expiration delay')
        )));

        $this->addElement(new Zend_Form_Element_Checkbox(array(
            'name'  => 'quoteDraggableProducts',
            'id'    => 'draggable-products',
            'label' => $translator->translate('Enable products draggable')
        )));

        $this->addElement(new Zend_Form_Element_Checkbox(array(
            'name'  => 'enableQuoteDefaultType',
            'id'    => 'enable-quote-default-type',
            'label' => $translator->translate('Enable quote payment type')
        )));

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'quotePaymentType',
            'id'           => 'quote-payment-types',
            'label'        => $translator->translate('Quote payment types'),
            'class'        => 'grid_6 alpha',
            'multiOptions' => $quotePaymentTypes
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'quotePartialPercentage',
            'id'    => 'quote-partial-percentage',
            'class' => 'grid_6 alpha',
            'label' => $translator->translate('Partial payment percentage')
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'quoteDownloadLabel',
            'id'    => 'quote-download-label',
            'class' => 'grid_6 alpha',
            'label' => $translator->translate('Quote download label'),
            'placeholder' => 'Proposal-quote'
        )));

        $this->addElement(new Zend_Form_Element_Checkbox(array(
            'name'  => 'enabledPartialPayment',
            'id'    => 'enabled-partial-payment',
            'label' => $translator->translate('Accept partial payments for quote: Yes/No'),
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'  => 'partialNotifyAfterQuantity',
            'id'    => 'partial-notify-after-quantity',
            'label' => $translator->translate('Lag time'),
            'placeholder' => 'partial payment lag time',
            'class' => 'grid_6 alpha'
        )));

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'partialNotifyAfterType',
            'id'           => 'partial-notify-after-type',
            'label' => $translator->translate('Length unit'),
            'class' => 'grid_6 alpha',
            'multiOptions' => array(
                'day' => $translator->translate('Days'),
                'month' => $translator->translate('Months')
            )
        )));

        $this->setDecorators(array('FormElements', 'Form'))
            ->setElementDecorators(array(
                'ViewHelper',
                array('Label', array('class' => 'grid_6')),
                array('HtmlTag', array('tag' => 'p'))
            ));

        $this->addElement(new Zend_Form_Element_Button(array(
            'name'       => 'applySettings',
            'label'      => $translator->translate('Update quote configuration'),
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
