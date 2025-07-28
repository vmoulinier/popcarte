<?php

require_once(ROOT_DIR . 'lib/Email/Messages/ReservationEmailMessage.php');

class ReservationUpdatedEmail extends ReservationEmailMessage
{
    public function Subject()
    {
        return $this->Translate('ReservationUpdatedAdminSubjectWithResource', [$this->primaryResource->GetName()]);
    }

    protected function GetTemplateName()
    {
        return 'ReservationCreated.tpl';
    }
}
