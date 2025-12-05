<?php
/**
 * MAGICSPACE: quotenotshowcontent
 * {quotenotshowcontent}{/quotenotshowcontent} - return content if empty option
 * Class MagicSpaces_Quotenotshowcontent_Quotenotshowcontent
 */

class MagicSpaces_Quotenotshowcontent_Quotenotshowcontent extends Tools_MagicSpaces_Abstract
{

    protected function _run()
    {

        if (!empty($this->_params[0])) {
            return '';
        }

        return $this->_spaceContent;
    }


}
