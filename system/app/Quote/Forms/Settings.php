<?php
class  Quote_Forms_Settings extends Zend_Form {

	public function init() {

		$this->setAttribs(array(
				'id'     => 'quote-settings',
				'class'  => 'quote-settings _fajax',
				'method' => Zend_Form::METHOD_POST,
				'action' => $this->getView()->websiteUrl . 'plugin/quote/run/settings/'
		));

		$this->addElement(new Zend_Form_Element_Checkbox(array(
			'name'  => 'autoQuote',
			'id'    => 'auto-quote',
			'label' => 'Generate quote automatically'
		)));

		$this->addElement(new Zend_Form_Element_Select(array(
			'name'  => 'quoteMailTemplate',
			'id'    => 'quote-mail-template',
			'label' => 'Quote mail template',
			'class' => 'grid_6',
			'multiOptions' => Tools_Mail_Tools::getMailTemplatesHash()
		)));

		$this->addElement(new Zend_Form_Element_Select(array(
			'name'  => 'quoteTemplate',
			'id'    => 'quote-template',
			'label' => 'Quote template',
			'class' => 'grid_6',
			'multiOptions' => Tools_System_Tools::getTemplatesHash(Quote_Models_Model_Quote::TEMPLATE_TYPE_QUOTE)
		)));

		$this->addElement(new Zend_Form_Element_Submit(array(
			'name'    => 'applySettings',
			'label'   => 'Save settings',
			'igonore' => true
		)));

		$this->setDecorators(array('FormElements', 'Form'))
			->setElementDecorators(array(
				'ViewHelper',
				array('Label', array('class' => 'grid_6')),
				array('HtmlTag', array('tag' => 'div'))
			));

		$this->getElement('autoQuote')->setDecorators(array(
			'ViewHelper',
			array('Label', array('class' => 'grid_8'))
		));
	}
}
