<?php
/**
 * Render and save new quote's content
 *
 * @author iamne Eugene I. Nezhuta <eugene@seotoaster.com>
 */

class Quote_Tools_Watchdog implements Interfaces_Observer {

	public function notify($object) {
		Quote_Models_Mapper_QuoteMapper::getInstance()->save($object->setContent($this->_generateQuoteContent()));
	}

	protected function _generateQuoteContent() {
		$templateMapper = Application_Model_Mappers_TemplateMapper::getInstance();
		$websiteHelper  = Zend_Controller_Action_HelperBroker::getStaticHelper('website');
		$configHelper   = Zend_Controller_Action_HelperBroker::getStaticHelper('config');
		$shoppingConfig = Models_Mapper_ShoppingConfig::getInstance()->getConfigParams();
		$themeData      = Zend_Registry::get('theme');
		$page           = new Application_Model_Models_Page();

		// getting quote template from shopping config
		if(!isset($shoppingConfig['quoteTemplate']) || !$shoppingConfig['quoteTemplate']) {
			//if quote template not secified in configs, get first template by type 'quote'
			$quoteTemplates = $templateMapper->findByType(Application_Model_Models_Template::TYPE_QUOTE);
			$quoteTemplate  = reset($quoteTemplates);
			unset($quoteTemplates);
		} else {
			$quoteTemplate = $templateMapper->findByName($shoppingConfig['quoteTemplate']);
		}

		$parser = new Tools_Content_Parser($quoteTemplate->getContent(), $page->toArray(), array(
			'websiteUrl'   => $websiteHelper->getUrl(),
			'websitePath'  => $websiteHelper->getPath(),
			'currentTheme' => $configHelper->getConfig('currentTheme'),
			'themePath'    => $themeData['path']
		));

		unset($themeData);
		unset($configHelper);
		unset($shoppingConfig);
		unset($templateMapper);
		unset($page);

		return $parser->parse();
	}
}
