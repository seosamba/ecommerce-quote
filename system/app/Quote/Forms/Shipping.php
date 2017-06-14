<?php

class Quote_Forms_Shipping extends Forms_Checkout_Shipping {

	public function init() {
		parent::init();

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
                'zip',
                'phonecountrycode',
                'phone',
                'mobile'
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

	}



}
