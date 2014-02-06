<?php
/**
 * GarbageCollector
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 6/29/12
 * Time: 3:33 PM
 */
class Quote_Tools_GarbageCollector extends Tools_System_GarbageCollector {

    protected function _runOnDefault() {}

    private function _cleanCache($page) {
        if ($page instanceof Application_Model_Models_Page) {
            $cacheTags = array(preg_replace('/[^\w\d_]/', '', $page->getTemplateId()), 'pageid_'.$page->getId());
            Zend_Controller_Action_HelperBroker::getStaticHelper('cache')->clean('', '', $cacheTags);
            unset($page);
        }
    }

    /**
     * Clean quote page cache
     *
     * _object represents Quote_Models_Model_Quote object and it's id is equal to it's page url
     */
    protected function _runOnUpdate() {
        $page = Application_Model_Mappers_PageMapper::getInstance()->findByUrl($this->_object->getId().'.html');
        $this->_cleanCache($page);
        unset($page);
    }

    /**
     * Removes a page quote and clean page cache
     */
    protected function _runOnDelete() {
        $pageMapper = Application_Model_Mappers_PageMapper::getInstance();
        if ($page = $pageMapper->findByUrl($this->_object->getId().'.html')) {
            $pageMapper->delete($page);
            $this->_cleanCache($page);
        }
        unset($page);
    }
}
