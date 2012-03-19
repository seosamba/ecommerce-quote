<?php
class  Quote_Forms_Settings extends Zend_Form {

	public function init() {

		$this->setAttribs(array(
				'id'     => 'quote-settings',
				'class'  => 'quote-settings _fajax',
				'method' => Zend_Form::METHOD_POST,
				'action' => $this->getView()->websiteUrl . 'plugin/quote/run/settings/'
			))
			->setDecorators(array('FormElements', 'Form'))
			->setElementDecorators(array(
				'ViewHelper',
				'Label',
				array('HtmlTag', array('tag' => 'div'))
			));

		$this->addElement(new Zend_Form_Element_Checkbox(array(
			'name'  => 'autoQuote',
			'id'    => 'auto-quote',
			'label' => 'Generate quote automatically'
		)));
	}

}
