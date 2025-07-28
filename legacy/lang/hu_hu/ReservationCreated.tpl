A foglalás részletei:
<br/>
<br/>

Kezdés: {formatdate date=$StartDate key=reservation_email}<br/>
Befejezés: {formatdate date=$EndDate key=reservation_email}<br/>
{if $ResourceNames|default:array()|count > 1}
	Elemek:
	<br/>
	{foreach from=$ResourceNames item=resourceName}
		{$resourceName}
		<br/>
	{/foreach}
{else}
	Elem: {$ResourceName}
	<br/>
{/if}

{if $ResourceImage}
	<div class="resource-image"><img src="{$ScriptUrl}/{$ResourceImage}"/></div>
{/if}

Megnevezés: {$Title}<br/>
Leírás: {$Description|nl2br}

{if count($RepeatRanges) gt 0}
	<br/>
	Foglalása az alábbi dátumokra esik:
	<br/>
{/if}

{foreach from=$RepeatRanges item=date name=dates}
	{formatdate date=$date->GetBegin()}
    {if !$date->IsSameDate()} - {formatdate date=$date->GetEnd()}{/if}
	<br/>
{/foreach}

{if $Participants|default:array()|count >0}
    <br/>
    Résztvevők:
    {foreach from=$Participants item=user}
        {$user->FullName()} <a href="mailto:{$user->EmailAddress()}">{$user->EmailAddress()}</a>
        <br/>
    {/foreach}
{/if}

{if $ParticipatingGuests|default:array()|count >0}
    {foreach from=$ParticipatingGuests item=email}
        <a href="mailto:{$email}">{$email}</a>
        <br/>
    {/foreach}
{/if}

{if $Invitees|default:array()|count >0}
    <br/>
    Meghívottak:
    {foreach from=$Invitees item=user}
        {$user->FullName()} <a href="mailto:{$user->EmailAddress()}">{$user->EmailAddress()}</a>
        <br/>
    {/foreach}
{/if}

{if $InvitedGuests|default:array()|count >0}
    {foreach from=$InvitedGuests item=email}
        <a href="mailto:{$email}">{$email}</a>
        <br/>
    {/foreach}
{/if}

{if $Accessories|default:array()|count > 0}
	<br/>
	Kiegészítők:
	<br/>
	{foreach from=$Accessories item=accessory}
		({$accessory->QuantityReserved}) {$accessory->Name}
		<br/>
	{/foreach}
{/if}

{if $CreditsCurrent > 0}
    <br/>
    A foglalás költsége {$CreditsCurrent} credits.
    {if $CreditsCurrent != $CreditsTotal}
        Ezen sorozat költsége {$CreditsTotal} credits.
    {/if}
{/if}

{if $Attributes|default:array()|count > 0}
	<br/>
	{foreach from=$Attributes item=attribute}
		<div>{control type="AttributeControl" attribute=$attribute readonly=true}</div>
	{/foreach}
{/if}

{if $RequiresApproval}
	<br/>
	A foglalások legalább egyike engedélyezést igényel. A fogalás függőben marad jóváhagyásig.
{/if}

{if $CheckInEnabled}
	<br/>
	A bejegyzett foglalások legalább egyike be és kijelentkezést igényel.
	{if $AutoReleaseMinutes != null}
		Ez a foglalás törlésre kerül, amennyiben nem jelentkezik be {$AutoReleaseMinutes} perccel az ütemezett kezdés után.
	{/if}
{/if}

{if !empty($ApprovedBy)}
	<br/>
	Jóváhagyta: {$ApprovedBy}
{/if}


{if !empty($CreatedBy)}
	<br/>
	Létrehozta: {$CreatedBy}
{/if}

<br/>
Referenciaszám: {$ReferenceNumber}

<br/>
<br/>
<a href="{$ScriptUrl}/{$ReservationUrl}">Fogalás megtekintése</a> |
<a href="{$ScriptUrl}/{$ICalUrl}">Naptárhoz adás</a> |
<a href="http://www.google.com/calendar/event?action=TEMPLATE&text={$Title|escape:'url'}&dates={formatdate date=$StartDate->ToUtc() key=google}/{formatdate date=$EndDate->ToUtc() key=google}&ctz={$StartDate->Timezone()}&details={$Description|escape:'url'}&location={$ResourceName|escape:'url'}&trp=false&sprop=&sprop=name:"
   target="_blank" rel="nofollow">Google Naptárhoz adás</a> |
<a href="{$ScriptUrl}">Bejelentkezés ide: {$AppTitle}</a>
