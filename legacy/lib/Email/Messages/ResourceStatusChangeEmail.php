<?php

require_once(ROOT_DIR . 'lib/Email/namespace.php');

class ResourceStatusChangeEmail extends EmailMessage
{
    private $to;
    /**
     * @var BookableResource
     */
    private $resource;
    private $message;

    /**
     * @param string $to
     * @param BookableResource $resource
     * @param string $message
     * @param string $language
     */
    public function __construct($to, BookableResource $resource, $message, $language)
    {
        parent::__construct($language);
        $this->to = $to;
        $this->resource = $resource;
        $this->message = $message;
    }

    public function To()
    {
        return new EmailAddress($this->to);
    }

    public function Subject()
    {
        return $this->Translate('ResourceStatusChangedSubject', [$this->resource->GetName()]);
    }

    public function Body()
    {
        $this->Set('ResourceName', $this->resource->GetName());
        $this->Set('Message', $this->message);
        return $this->FetchTemplate('ResourceStatusChanged.tpl');
    }
}
