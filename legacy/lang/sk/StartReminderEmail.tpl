Nezmeškajte rezervovaný termín.<br/>
Detaily rezervácie:
	<br/>
	<br/>
	Začiatok: {formatdate date=$StartDate key=reservation_email}<br/>
	Koniec: {formatdate date=$EndDate key=reservation_email}<br/>
	Ihrisko: {$ResourceName}<br/>
	Názov: {$Title}<br/>
	Popis: {$Description|nl2br}<br/>
<br/>
<a href="{$ScriptUrl}/{$ReservationUrl}">Zobraziť túto rezerváciu v systéme</a> |
	<a href="{$ScriptUrl}/{$ICalUrl}">Pridať do Outlook-u</a> |
	<a href="{$ScriptUrl}">Prihlásiť sa do rezervačného systému</a>
