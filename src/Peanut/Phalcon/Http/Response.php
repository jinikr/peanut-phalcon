<?php
namespace Peanut\Phalcon\Http;

class Response extends \Phalcon\Http\Response
{
    /**
     * @param  $content
     * @return mixed
     */
    public function setJsonContent($content)
    {
        if (!parent::getHeaders()->get('Content-Type')) {
            parent::setContentType('application/json', 'UTF-8');
        }

        parent::setJsonContent($content);

        return $this;
    }

    public function getJsonContent()
    {
        return json_decode(parent::getContent(), true);
    }
}
