<?php

require_once(ROOT_DIR . 'Presenters/Reservation/ReservationAttributesPresenter.php');

interface IReservationAttributesPage
{
    /**
     * @return int
     */
    public function GetRequestedUserId();

    /**
     * @return int
     */
    public function GetRequestedReferenceNumber();

    /**
     * @param Attribute[] $attributes
     */
    public function SetAttributes($attributes);

    /**
     * @return int[]
     */
    public function GetRequestedResourceIds();
}

class ReservationAttributesPage extends Page implements IReservationAttributesPage
{
    /**
     * @var ReservationAttributesPresenter
     */
    private $presenter;

    public function __construct()
    {
        parent::__construct();

        $authorizationService = new AuthorizationService(new UserRepository());
        $this->presenter = new ReservationAttributesPresenter(
            $this,
            new AttributeService(new AttributeRepository()),
            $authorizationService,
            new PrivacyFilter(new ReservationAuthorization($authorizationService)),
            new ReservationViewRepository()
        );
    }

    public function PageLoad()
    {
        $userSession = ServiceLocator::GetServer()->GetUserSession();
        $this->presenter->PageLoad($userSession);
        $this->Set('ReadOnly', BooleanConverter::ConvertValue($this->GetIsReadOnly()));
        $this->Display('Ajax/reservation/reservation_attributes.tpl');
    }

    /**
     * @param Attribute[] $attributes
     */
    public function SetAttributes($attributes)
    {
        $this->Set('Attributes', $attributes);
    }

    /**
     * @return int
     */
    public function GetRequestedUserId()
    {
        return $this->GetQuerystring(QueryStringKeys::USER_ID);
    }

    /**
     * @return int[]
     */
    public function GetRequestedResourceIds()
    {
        $resourceIds = $this->GetQuerystring(QueryStringKeys::RESOURCE_ID);
        if (is_array($resourceIds)) {
            return $resourceIds;
        }

        return [];
    }

    /**
     * @return string
     */
    public function GetRequestedReferenceNumber()
    {
        return $this->GetQuerystring(QueryStringKeys::REFERENCE_NUMBER);
    }

    private function GetIsReadOnly()
    {
        return $this->GetQuerystring(QueryStringKeys::READ_ONLY);
    }
}
