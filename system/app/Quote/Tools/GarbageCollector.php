<?php
/**
 * GarbageCollector
 *
 * @author: iamne <eugene@seotoaster.com> Seotoaster core team
 * Date: 6/29/12
 * Time: 3:33 PM
 */
class Quote_Tools_GarbageCollector extends Tools_System_GarbageCollector {

    protected function _runOnDefault() {

    }

    /**
     * Clean quote page cache
     *
     * _object represents Quote_Models_Model_Quote object and it's id is equal to it's page url
     *
     */
    protected function _runOnUpdate() {
        $cacheHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('cache');
        $page        =  Application_Model_Mappers_PageMapper::getInstance()->findByUrl($this->_object->getId() . '.html');
        if($page instanceof Application_Model_Models_Page) {
            $cacheTags   = array(
                preg_replace('/[^\w\d_]/', '', $page->getTemplateId()),
                'pageid_' . $page->getId()
            );
            $cacheHelper->clean('', '', $cacheTags);
        }
        unset($page);
    }
}
