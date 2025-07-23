<div id="reservationParticipation">

	<div class="d-flex align-items-center gap-1 mb-2">
		<label class="fw-bold mb-0" for="inviteeAutocomplete">{translate key="InvitationList"}<span
				class="badge bg-secondary ms-1" id="inviteeBadge">0</span></label>

	</div>
	<div class="d-flex align-items-center flex-wrap gap-1">
		<div class="participationText d-flex align-items-center flex-wrap gap-1">
			<span class="fw-bold d-none d-sm-inline-block">{translate key=Add}</span>
			<input type="search" id="inviteeAutocomplete" class="form-control form-control-sm w-auto user-search"
				placeholder="{translate key=NameOrEmail}" />
			<span class="d-none d-sm-inline-block vr m-2"></span>
		</div>
		<div class="participationButtons d-flex align-items-center flex-wrap gap-1">
			<a href="#" id="promptForInvitees" class="link-primary me-2">
				<i class="bi bi-person-fill me-1"></i>{translate key='Users'}
			</a>
			<a id="promptForGroupInvitees" type="button" class="link-primary me-2">
				<i class="bi bi-people-fill me-1"></i>{translate key='Groups'}
			</a>
			{if $AllowGuestParticipation}
				<a href="#" id="promptForGuests" class="link-primary">
					<i class="bi bi-person-plus-fill me-1"></i>{translate key='Guest'}
				</a>
			{/if}
			<div id="allowParticipation" class="ms-1">
				<div class="form-check">
					<input class="form-check-input" type="checkbox" {if $AllowParticipantsToJoin}checked="checked" {/if}
						{formname key=ALLOW_PARTICIPATION} id="allowParticipationCheckbox">
					<label class="form-check-label"
						for="allowParticipationCheckbox">{translate key=AllowParticipantsToJoin}</label>
				</div>
			</div>
		</div>
	</div>

	<div id="inviteeList" class="rounded border bg-white my-2 p-2">
	</div>



	<div class="modal fade" id="inviteeDialog" tabindex="-1" role="dialog" aria-labelledby="inviteeModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="inviteeModalLabel">{translate key=InviteOthers}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
				</div>
				<div class="modal-body">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary"
						data-bs-dismiss="modal">{translate key='Done'}</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="inviteeGuestDialog" tabindex="-1" role="dialog" aria-labelledby="inviteeGuestModalLabel"
		aria-hidden="true">
		<div class="modal-dialog">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="inviteeGuestModalLabel">{translate key=InviteOthers}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
				</div>
				<div class="modal-body">
					<div class="form-group row">
						<label for="txtGuestEmail"
							class="col-2 form-control-label fw-bold align-items-center mb-0">{translate key=Email}</label>
						<div class="col-8">
							<input id="txtGuestEmail" type="email" class="form-control form-control-sm" />
						</div>
						<div class="col-2">
							<button id="btnAddGuest" class="btn btn-link link-primary" type="button"><span
									class="visually-hidden">{translate key='Guest'}</span><i
									class="bi bi-person-plus-fill icon add"></i></button>
						</div>
					</div>
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary"
						data-bs-dismiss="modal">{translate key='Done'}</button>
				</div>
			</div>
		</div>
	</div>

	<div class="modal fade" id="inviteeGroupDialog" tabindex="-1" role="dialog" aria-labelledby="inviteeGroupModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="inviteeGroupModalLabel">{translate key=InviteOthers}</h5>
					<button type="button" class="btn-close" data-bs-dismiss="modal" aria-hidden="true"></button>
				</div>
				<div class="modal-body">
				</div>
				<div class="modal-footer">
					<button type="button" class="btn btn-primary"
						data-bs-dismiss="modal">{translate key='Done'}</button>
				</div>
			</div>
		</div>
	</div>

</div>