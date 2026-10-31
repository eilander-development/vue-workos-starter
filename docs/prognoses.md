# Prognoses

De pagina `/prognoses` rekent twaalf toekomstige begrotingsmaanden door. Iedere
maand begint op nul. Structurele vrije ruimte = inkomsten − vaste uitgaven − sparen; banktransacties
en banksaldi doen niet mee.

## Gebruik

1. Kies een toekomstige startmaand en voeg aanpassingen aan bestaande inkomsten-
   of uitgavenposten toe. Een regel heeft een ingangsmaand en een bedrag erbij/eraf,
   percentage erbij/eraf of een nieuw vast bedrag.
2. Bekijk de vier KPI's en klik door de twaalf maanden. Regels worden op
   ingangsmaand verwerkt. Een later percentage gebruikt het resultaat van eerdere
   regels, zonder maandelijkse samengestelde verhoging.
3. Sla de prognose op. Zolang je niet hebt doorgevoerd, kun je dezelfde prognose
   opnieuw openen, regels wijzigen, toevoegen, verwijderen en aan- of uitzetten.
   'Wijzigingen opslaan' werkt dezelfde prognose bij en maakt geen kopie.
4. Controleer het overzicht en voer de bedragen eventueel door in de begroting.
5. Kopieer een prognose als je bewust een afzonderlijk scenario wilt maken met
   actuele gegevens. De ingangsmaanden verschuiven mee met de nieuwe startmaand. Reeds
   doorgevoerde regels staan standaard uit, maar kunnen bewust opnieuw aan.

Bij doorvoeren gaat de prognose op slot, inclusief haar regels. Ook na terugdraaien
blijft de oorspronkelijke prognose vergrendeld. Voor een nieuwe variant kun je
haar kopiëren. Niet-opgeslagen wijzigingen moeten eerst worden opgeslagen voordat
doorvoeren beschikbaar is.

Een bewerkbare prognose bewaart haar brongegevens totdat je bewust actuele
gegevens ophaalt. De bron bevat intern een tweede jaarcyclus om vaste jaarpatronen
te kunnen berekenen; de pagina toont maximaal twaalf maanden. Wijzigingen vanuit
een ander venster worden gecontroleerd met een revisiecode, zodat opslaan of
doorvoeren geen nieuwere versie overschrijft.

## Bedragbasis en inzicht in de berekening

De knop **Bekijk berekening en bronnen** toont per maand alle vaste posten,
hun gebruikte bronmaand, bedragen en of ze meetellen. Dezelfde bediening staat in
de postentabel. Posten zonder betaling in de geselecteerde maand blijven zichtbaar
als ze een bekend vast betalingspatroon hebben, zodat hun bedragbasis instelbaar is.

Per post kun je kiezen uit:

- **Maandprofiel behouden**: behoud de bedragen van de afzonderlijke betaalmaanden.
- **Laatste vaste bedrag**: gebruik het laatst gedateerde vaste begrotingsbedrag
  van vóór de startmaand voor iedere bestaande betaalmaand. Het betalingsritme
  blijft behouden. Expliciete bedragen of geplande wijzigingen in de toekomst
  krijgen voorrang.

Bij verschillende bronbedragen verschijnt een advies om deze keuze te controleren.
De app beslist niet zelf of verschillen een structurele verhoging of een bewust
maandprofiel voorstellen. De keuze wordt per post binnen de prognose bewaard en
bij kopiëren meegenomen; de brongegevens worden dan opnieuw opgehaald.

Een post kan voor de hele prognose of voor één maand worden uitgesloten. Dit is
een **berekeningsfilter**: het bedrag wordt zichtbaar op nul gezet in de prognose,
terwijl de oorspronkelijke begroting als vergelijking behouden blijft. Het filter
verwijdert geen werkelijke begrotingsbedragen en verandert geen actieve regels.
Regels die je niet wilt doorvoeren, schakel je bij **Aanpassingen** uit.

Een gekozen laatste vaste bedrag wordt bij doorvoeren wel als vaste wijziging
vastgelegd, naast de actieve regels. Het controleoverzicht toont die wijzigingen
afzonderlijk en vermeldt expliciet wanneer berekeningsfilters actief zijn.
Zo kan een bedrag uit december doorlopen naar alle kwartaalbetalingen van het
volgende jaar, zonder andere maandprofielen automatisch vlak te trekken.

De startmaand en de ingangsmaanden moeten na de lopende rapportageperiode liggen.
De app gebruikt de ingestelde rapportagegrens, standaard de vijftiende.

## Extra kosten en historische referenties

Losse begrotingsregels en Klarna/creditcard tellen standaard niet mee in de
structurele KPI's. Historische bedragen worden alleen als referentie getoond, met
de bronmaand en de tekst **geen voorspelling**. Het gaat om historische
begrotingsbedragen, niet om bankbetalingen. Ongedateerde basisbedragen krijgen
een apart herkenbare bronvermelding.

Expliciet begrote extra kosten in de toekomstige maand worden in dezelfde modal
apart getoond. Alleen deze geplande extra's worden afgetrokken bij de aanvullende
uitkomst 'Na aftrek van expliciet geplande extra kosten'. Historische referenties
worden nooit afgetrokken en nooit als jaarlijks terugkerende kosten doorgevoerd.

Bij 'Meenemen in prognoses' in het bewerkscherm van een uitgavenpost kan de gebruiker
de automatische indeling overschrijven met **Vast** (bijvoorbeeld een vaste
maandelijkse aflossing) of **Extra**. De indeling wijzigt geen bestaande bedragen.
Inkomsten, vaste uitgaven en sparen hebben eigen tabelgroepen en subtotalen.

Nieuwe prognoses hebben berekeningsversie 3. Doorgevoerde prognoses met versie 1
behouden hun oorspronkelijke berekening met alle kosten. Oude bewerkbare prognoses
zonder bewaarde brongegevens halen bij openen de actuele begroting op; opslaan
werkt vervolgens dezelfde prognose bij met de structurele basis.

## Bedragen en historie

- `forecasts` bewaart de regels, oorspronkelijke gegevens, berekende uitkomsten,
  maker en tijdstippen van doorvoeren/terugdraaien. De berekening wordt op de server
  opnieuw uitgevoerd; bedragen uit de browser worden niet als waarheid opgeslagen.
- `budget_amount_changes` bewaart vaste bedragen voor de twaalf jaarlijkse
  maandposities, de ingangsmaand en eventueel een exclusieve eindmaand. Het
  percentage blijft alleen bij de prognoseregel staan als berekening en uitleg.
- `BudgetProjection` wordt zowel door de prognose als door de maandbegroting
  gebruikt. Expliciete maandbedragen gaan voor het basisbedrag. Als een jaar nog
  geen bedrag heeft, wordt het laatst bekende bedrag voor dezelfde kalendermaand
  overgenomen. Zo blijven ook kwartaalpatronen buiten januari/april/juli/oktober
  en jaarlijks wisselende bedragen behouden. Dit jaarlijkse patroon wordt zichtbaar
  gemaakt in de prognose voordat het wordt doorgevoerd.
- Alleen geplande vaste betaalmaanden worden aangepast. Een wijziging naar nul
  behoudt de betaalmaanden voor latere wijzigingen. Extra kosten blijven buiten
  de wijzigingsregels, tenzij de post bewust als Vast is ingesteld.
- Bekende latere wijzigingen blijven behouden: de nieuwe regel eindigt bij de
  eerstvolgende bestaande wijzigingsgrens. Een controlegetal over de brongegevens
  blokkeert opslaan of doorvoeren op verouderde gegevens.
- Latere handmatige bedragen hebben voorrang. Terugdraaien wordt geblokkeerd bij
  handmatige of later doorgevoerde wijzigingen op dezelfde posten. Gebruik dan een
  nieuwe prognose als correctie. Anders eindigen de wijzigingen vanaf de gekozen
  toekomstige maand. Afgelopen en lopende maanden blijven behouden.
- De historie staat bij een post in het bewerkscherm en via de prognosepagina.
  Nieuwe tabellen zijn opgenomen in de bestaande back-upvolgorde en identificatie.

## Database activeren

De nieuwe migratie hoort bij de **catalog**-verbinding. Voer voor een bestaande
installatie uitsluitend deze migratie uit:

```sh
php artisan migrate --database=catalog \
  --path=database/migrations/catalog/2026_10_31_120000_create_forecasts.php
```

De migratie voegt twee tabellen toe en wijzigt geen bestaande bedragen. De overige
app blijft beschikbaar wanneer deze migratie nog niet is uitgevoerd.

Voor de instelbare indeling per post is ook deze aanvullende migratie nodig:

```sh
php artisan migrate --database=catalog \
  --path=database/migrations/catalog/2026_10_31_140000_add_forecast_mode_to_budgets.php
```

Dit voegt `budgets.forecast_mode` toe met standaardwaarde `auto`.

## Controle

```sh
php artisan test --filter=ForecastTest
node --test tests/Frontend/forecast.test.mjs
npm run build
```

Gebruik PHP 8.4 of hoger, zoals vereist door de geïnstalleerde dependencies.
De featuretests gebruiken aparte testdatabases. Test nooit met de database uit
`.env` als testdatabase. Tests omvatten onder andere kwartaalpatronen, jaargrenzen,
opeenvolgende regels, vaste bedragen voorbij de kijkperiode, bewaarde momentopnamen,
verouderde gegevens en veilig terugdraaien.

Tijdelijke wijzigingen met eindmaand, nieuwe posten simuleren en vergelijking met
werkelijke transacties vallen buiten deze eerste versie.
