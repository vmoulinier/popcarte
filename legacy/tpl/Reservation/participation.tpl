<div id="reservationParticipation">
	<div class="d-flex align-items-center gap-1 mb-2">
		<label class="fw-bold mb-0" for="participantAutocomplete">{translate key="ParticipantList"}<span
				class="badge bg-secondary ms-1" id="participantBadge">0</span></label>
	</div>

	<div class="d-flex flex-wrap gap-1">
		<div class="participationText d-flex align-items-center flex-wrap gap-1">
			<span class="fw-bold d-none d-sm-inline-block">{translate key=Add}</span>
			<input type="search" id="participantAutocomplete" class="form-control form-control-sm w-auto user-search"
				placeholder="{translate key=NameOrEmail}" />
			<span class="d-none d-sm-inline-block vr m-2"></span>
		</div>
		<div class="participationButtons d-flex align-items-center flex-wrap gap-1">
			<a href="#" id="promptForParticipants" class="link-primary me-2">
				<i class="bi bi-person-fill"></i>
				{translate key='Users'}
			</a>
			<a href="#" id="promptForGroupParticipants" class="link-primary">
				<i class="bi bi-people-fill"></i>
				{translate key='Groups'}
			</a>
		</div>
	</div>

	<div id="participantList" class="rounded border bg-white my-2 p-2">
	</div>


	<div class="modal fade" id="participantDialog" tabindex="-1" role="dialog" aria-labelledby="participantModalLabel"
		aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="participantModalLabel">{translate key=AddParticipants}</h5>
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

	<div class="modal fade" id="participantGroupDialog" tabindex="-1" role="dialog"
		aria-labelledby="participantGroupModalLabel" aria-hidden="true">
		<div class="modal-dialog modal-dialog-scrollable">
			<div class="modal-content">
				<div class="modal-header">
					<h5 class="modal-title" id="participantGroupModalLabel">{translate key=AddParticipants}</h5>
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