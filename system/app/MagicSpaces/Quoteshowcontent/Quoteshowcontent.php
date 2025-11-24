<?php
/**
 * MAGICSPACE: quoteshowcontent
 * {quoteshowcontent}{/quoteshowcontent} - return content if empty option
 * Class MagicSpaces_Quoteshowcontent_Quoteshowcontent
 */

class MagicSpaces_Quoteshowcontent_Quoteshowcontent extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {

        if (!empty($this->_params[0])) {
            return '';
        }

        return $this->_spaceContent;
    }


}
