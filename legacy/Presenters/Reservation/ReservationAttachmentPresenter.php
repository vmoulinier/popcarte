<?php

require_once(ROOT_DIR . 'lib/Common/namespace.php');
require_once(ROOT_DIR . 'lib/Application/Reservation/namespace.php');
require_once(ROOT_DIR . 'Domain/Access/namespace.php');
require_once(ROOT_DIR . 'Pages/Reservation/ReservationAttachmentPage.php');

class ReservationAttachmentPresenter
{
    /**
     * @var IReservationAttachmentPage
     */
    private $page;

    /**
     * @var IReservationRepository
     */
    private $reservationRepository;

    /**
     * @var IPermissionService
     */
    private $permissionService;

    public function __construct(IReservationAttachmentPage $page, IReservationRepository $reservationRepository, IPermissionService $permissionService)
    {
        $this->page = $page;
        $this->reservationRepository = $reservationRepository;
        $this->permissionService = $permissionService;
    }

    public function PageLoad(UserSession $currentUser)
    {
        $loaded = $this->TryPageLoad($currentUser);
        if ($loaded === false) {
            $this->page->ShowError();
        } else {
            $this->page->BindAttachment($loaded);
        }
    }

    private function TryPageLoad($currentUser)
    {
        $fileId = $this->page->GetFileId();
        $referenceNumber = $this->page->GetReferenceNumber();
        Log::Debug('Trying to load reservation attachment. FileId: %s, ReferenceNumber %s', $fileId, $referenceNumber);

        $attachment = $this->reservationRepository->LoadReservationAttachment($fileId);
        if ($attachment == null) {
            Log::Error('Error loading resource attachment, attachment not found');
            return false;
        }

        $reservation = $this->reservationRepository->LoadByReferenceNumber($referenceNumber);
        if ($reservation == null) {
            Log::Error('Error loading resource attachment, reservation not found');
            return false;
        }

        if ($reservation->SeriesId() != $attachment->SeriesId()) {
            Log::Error('Error loading resource attachment, attachment not associated with reservation');
            return false;
        }

        if (!$this->permissionService->CanAccessResource(new ReservationResource($reservation->ResourceId()), $currentUser)) {
            Log::Error('Error loading resource attachment, insufficient permissions');
            return false;
        }

        return $attachment;
    }
}
