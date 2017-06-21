<?php

class Quote_Forms_Shipping extends Forms_Checkout_Shipping {

	public function init() {
		parent::init();

        $this->addElement(new Zend_Form_Element_Select(array(
            'name'         => 'mobilecountrycode',
            'label'        => 'Mobile',
            'multiOptions' => Tools_System_Tools::getCountryPhoneCodesList(true, array(), true),
            'value'        => Models_Mapper_ShoppingConfig::getInstance()->getConfigParam('country'),
            'style'        => 'width: 41.667%;',
        )));

        $this->addElement(new Zend_Form_Element_Text(array(
            'name'     => 'mobile',
            'label'    => null,
            'value'    => '',
            'style'    => 'width: 58.333%;'
        )));

        $this->addDisplayGroups(array(
            'lcol' => array(
                'firstname',
                'lastname',
                'company',
                'email',
                'address1',
                'address2'
            ),
            'rcol' => array(
                'country',
                'city',
                'state',
                'zip'
            ),
            'bottom' => array('shippingInstructions')
        ));

        $this->getDisplayGroup('lcol')
            ->setDecorators(array(
                'FormElements',
                'Fieldset'
            ));

        $this->getDisplayGroup('rcol')
            ->setDecorators(array(
                'FormElements',
                'Fieldset'
            ));

        $this->getDisplayGroup('bottom')
            ->setDecorators(array(
                'FormElements',
                'Fieldset'
            ));

        $this->setElementDecorators(array(
            'ViewHelper',
            'Label',
            array('HtmlTag', array('tag' => 'p'))
        ));

        $this->getElement('phone')->removeDecorator('HtmlTag');
        $this->getElement('phonecountrycode')->removeDecorator('HtmlTag');
        $this->getElement('mobile')->removeDecorator('HtmlTag');
        $this->getElement('mobilecountrycode')->removeDecorator('HtmlTag');

        $this->addDisplayGroup(array(
            'mobilecountrycode',
            'mobile'
        ),'mobilesShippingBlock',array('HtmlTag', array('tag' => 'div')));

        $this->addDisplayGroup(array(
            'phonecountrycode',
            'phone'
        ),'phonesShippingBlock',array('HtmlTag', array('tag' => 'div')));

        $mobilesBlock = $this->getDisplayGroup('mobilesShippingBlock');
        $mobilesBlock->setDecorators(array(
            'FormElements',
            array('HtmlTag',array('tag'=>'p', 'class' => 'mobile-desktop-phone-block'))
        ));

        $phonesBlock = $this->getDisplayGroup('phonesShippingBlock');
        $phonesBlock->setDecorators(array(
            'FormElements',
            array('HtmlTag',array('tag'=>'p', 'class' => 'mobile-desktop-phone-block'))
        ));

	}



}
