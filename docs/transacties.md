# Transacties, reserveringen en historie

Banktransacties hebben drie zichtbare statussen: **Geboekt**, **Reservering** en
**Historie**. Reserveringen worden direct opgeslagen en kunnen dezelfde
koppelregels en splitsingen krijgen als definitieve betalingen. De transactiepagina
toont geboekte en gereserveerde bedragen afzonderlijk en heeft een statusfilter.

Alleen geboekte transacties tellen als ontvangen/betaald, in werkelijke cashflow,
betaalritmes en potcompensatie. Een gekoppelde reservering maakt een post dus nog
niet betaald. Het live banksaldo wordt niet aangepast en reserveringen worden
niet nogmaals van het beschikbare banksaldo afgetrokken.

## Definitief verwerken

De importer herkent een betaling aan de stabiele bankreferentie en eigen rekening.
Bij dezelfde identiteit en hetzelfde bedrag mag een voorlopige datum veranderen
naar de definitieve boekdatum. De bestaande transactie houdt haar ID, koppelingen,
spaarpottoewijzing en splitsingen. De oude reservering krijgt een historische kopie.
Een herhaalde levering maakt geen nieuwe betaling of historische kopie. Een later
aangeleverde reservering kan een definitieve boeking niet weer voorlopig maken.

Bestaat er door een oudere import al een definitieve kopie, dan blijft die actief.
Ontbrekende koppelingen worden overgenomen en de voorlopige kopie wordt historie.
Bevestigde dubbele boekingen mogen op dezelfde manier worden gearchiveerd.
Daarvoor moeten referentie, eigen rekening, bedrag en definitieve datum overeenkomen.
Tegenstrijdige koppelingen, splitsingen of expliciete uitsluitingen blokkeren samenvoegen.

Een gewijzigd bedrag of gewijzigde definitieve boekdatum vraagt controle in het
synchronisatierapport. Bij ontbrekende identiteit of ongeldige gegevens wordt de
bankregel niet geïmporteerd. Bij een andere referentie worden betalingen nooit
alleen op gelijke naam, bedrag of datum samengevoegd. Een verdwenen reservering
wordt niet automatisch als betaald of vervallen beschouwd.

## Bewaren en bekijken

De knop **Historie** op de transactiepagina opent een doorzoekbaar overzicht met
paginering. Vanuit transactiedetails kun je de historie van één transactie bekijken.
De oorspronkelijke bankpayload, koppelingen en splitsingen blijven bewaard, met
reden en verwijzing naar de actieve transactie. Nieuwe historische registraties
bewaren ook de toenmalige namen van categorie/post en eventuele afgeleide splits.

## Reserveringen beoordelen

Via **Beoordelen** bij een open bankreservering kun je vaststellen dat een andere
definitieve registratie dezelfde betaling is, ook wanneer de bankreferentie
veranderd is. Het scherm toont beide bankreferenties, bedragen en splitsingen.
Kandidaten komen alleen van dezelfde eigen rekening en betaalrichting. Zonder
zoekterm wordt rond de reserveringsdatum gezocht; met een omschrijving ook daarbuiten.
Een kandidaat is geen automatische bevestiging: je kiest en bevestigt zelf.

Ontbrekende koppelingen kun je samenvoegen. Bij tegenstrijdige koppelingen kies je
bewust de reservering of de definitieve betaling als bron. Het definitieve
bankbedrag, de boekdatum en bankpayload veranderen niet. Bij een gewijzigd bedrag
moet je het evenredig aanpassen van een overgenomen splitsing expliciet bevestigen;
de nieuwe verdeling wordt vooraf getoond. De oorspronkelijke reservering en de
vorige koppelingen van de definitieve betaling blijven in Historie.

Een werkelijk vervallen reservering kun je via hetzelfde scherm als vervallen
archiveren. Alleen ontbreken in een volgende banklevering is daarvoor onvoldoende.
Dezelfde voorlopige levering maakt haar na deze keuze niet opnieuw actief.
Levert de bank later alsnog een definitieve boeking, dan wordt die wel geïmporteerd.
Een handmatig bevestigde koppeling blijft ook bij herhaalde bankleveringen bekend.
Afwijkende definitieve gegevens worden ter controle geblokkeerd.

Beoordelen controleert voor beide transacties of de gegevens sinds het laden zijn
gewijzigd. Bij gelijktijdige verwerking of aanpassing worden geen oude gegevens
overschreven; je moet de beoordeling opnieuw laden. Historie gaat nooit via dit
scherm terug naar actief.

Een globale modelscope sluit historische registraties uit van normale queries,
totalen, koppelingen en automatische regels. Alleen expliciete historiequeries
en back-ups lezen ze mee. Historie is niet via de normale schrijf-API te wijzigen
of opnieuw te activeren. Bij koppelen vanuit een verouderde browser kan de oude
reserveringsstatus de definitieve bankgegevens niet terugzetten.

De synchronisatie toont normaal een samenvatting van nieuwe betalingen,
reserveringen, definitief verwerkte reserveringen en gearchiveerde kopieën. Alleen
geblokkeerde gevallen openen automatisch het rapport. Opgeloste meldingen uit een
ouder sessierapport worden niet opnieuw als openstaand getoond.

## Bestaande data

De ledger-migratie `2026_11_01_190000_add_transaction_history.php` voegt historievelden
toe en herstelt oude reserveringsvlaggen op basis van de opgeslagen bankstatus.

`php artisan transactions:reconcile` controleert bestaande registraties zonder ze
te wijzigen. Met `--apply` archiveert het commando uitsluitend bewezen kopieën,
met dezelfde controles als de importer. Het is herhaalbaar; onzekere groepen blijven
ongewijzigd. Er worden geen banktransacties verwijderd en banksaldi blijven behouden.
