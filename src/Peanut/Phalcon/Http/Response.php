<?php
namespace Peanut\Phalcon\Http;

class Response extends \Phalcon\Http\Response
{
    /**
     * @param  array   $content
     * @return $this
     */
    public function setJsonContent($content)
    {
        if (!parent::getHeaders()->get('Content-Type')) {
            parent::setContentType('application/json', 'UTF-8');
        }

        parent::setJsonContent($content);

        return $this;
    }

    /**
     * @return array
     */
    public function getJsonContent()
    {
        return json_decode(parent::getContent(), true);
    }
}
