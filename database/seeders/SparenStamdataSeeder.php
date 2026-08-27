<?php

namespace Database\Seeders;

use App\Models\Budget;
use App\Models\BudgetMonthValue;
use App\Models\Category;
use App\Models\ImportRule;
use App\Models\SavingsGoal;
use App\Models\Transaction;
use App\Services\SparenStateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class SparenStamdataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->warn('Catalogus staat in database/catalog.sqlite (git). Deze seeder overschrijft die data.');
        $this->command?->warn('Niet gebruiken als bron van waarheid; alleen als nood-herstel.');
        Schema::disableForeignKeyConstraints();
        Schema::connection('catalog')->disableForeignKeyConstraints();
        Transaction::query()->delete();
        ImportRule::query()->delete();
        BudgetMonthValue::query()->delete();
        Budget::query()->delete();
        Category::query()->delete();
        SavingsGoal::query()->delete();
        Schema::connection('catalog')->enableForeignKeyConstraints();
        Schema::enableForeignKeyConstraints();

        $categories = [
            ['key' => 'cat-inkomsten', 'name' => 'Inkomsten', 'type' => 'income', 'icon' => 'TrendingUp', 'color' => 'emerald', 'description' => 'Salaris, kinderbijslag, kindgebonden budget en overige toeslagen'],
            ['key' => 'cat-woning', 'name' => 'Woning', 'type' => 'expense', 'icon' => 'Building2', 'color' => 'blue', 'description' => 'Hypotheek/huur, energie, water, gemeentelijke belastingen en internet'],
            ['key' => 'cat-dagelijks', 'name' => 'Dagelijks Leven', 'type' => 'expense', 'icon' => 'ShoppingBag', 'color' => 'purple', 'description' => 'Boodschappen, huishouden, persoonlijke verzorging en kleding'],
            ['key' => 'cat-vervoer', 'name' => 'Vervoersmiddelen', 'type' => 'expense', 'icon' => 'Car', 'color' => 'cyan', 'description' => 'Brandstof, motorrijtuigenbelasting, autoverzekering en openbaar vervoer'],
            ['key' => 'cat-verzekeringen', 'name' => 'Verzekeringen', 'type' => 'expense', 'icon' => 'ShieldCheck', 'color' => 'indigo', 'description' => 'Zorgverzekering, inboedel, aansprakelijkheid en rechtsbijstand'],
            ['key' => 'cat-spaargeld', 'name' => 'Spaargeld', 'type' => 'saving', 'icon' => 'PiggyBank', 'color' => 'emerald', 'description' => 'Reserveringen voor noodbuffer, vakantie, woningonderhoud en spaardoelen'],
            ['key' => 'cat-leningen', 'name' => 'Leningen', 'type' => 'expense', 'icon' => 'Receipt', 'color' => 'amber', 'description' => 'Studieschuld, persoonlijke leningen, creditcard en aflossingen'],
            ['key' => 'cat-overig-vast', 'name' => 'Overige Vaste Kosten', 'type' => 'expense', 'icon' => 'Tags', 'color' => 'pink', 'description' => 'Vaste abonnementen (Netflix, Spotify, sportclub, loterijen)'],
            ['key' => 'cat-overig', 'name' => 'Overige Kosten', 'type' => 'expense', 'icon' => 'Tags', 'color' => 'rose', 'description' => 'Onvoorziene incidentele uitgaven en cadeaus'],
        ];

        $categoryIds = [];
        foreach ($categories as $index => $row) {
            $category = Category::query()->create([
                ...$row,
                'slug' => $row['key'],
                'is_default' => true,
            ]);
            $categoryIds[$row['name']] = $category->id;
        }

        $items = [
            ['ink-1', 'Mark (Beekman)', 'Inkomsten', 3975.66],
            ['ink-2', 'Kindgebonden budget', 'Inkomsten', 184],
            ['ink-3', 'Kinderbijslag', 'Inkomsten', 0],
            ['ink-4', 'Voorlopige teruggave', 'Inkomsten', 559.5],
            ['won-1', 'Electriciteit (GreenChoice)', 'Woning', 56],
            ['won-2', 'Warmte (Ennatuurlijk)', 'Woning', 150],
            ['won-3', 'Water (Vitens + GBLT)', 'Woning', 68.1],
            ['won-4', 'KPN glasvezel', 'Woning', 78],
            ['dag-1', 'Boodschappen', 'Dagelijks Leven', 500],
            ['dag-2', 'Telefoon', 'Dagelijks Leven', 30],
            ['dag-3', 'Kinderalimentatie', 'Dagelijks Leven', 437.23],
            ['dag-4', 'Gemeentebelastingen', 'Dagelijks Leven', 102],
            ['dag-5', 'Zorgverzekering', 'Dagelijks Leven', 0],
            ['verv-1', 'Wegenbelasting', 'Vervoersmiddelen', 129],
            ['verv-2', 'Verzekering', 'Vervoersmiddelen', 113.58],
            ['verv-3', 'Benzine', 'Vervoersmiddelen', 100],
            ['verz-1', 'Begrafenis (asr)', 'Verzekeringen', 12.68],
            ['verz-2', 'Woonverzekering (InShared)', 'Verzekeringen', 41.29],
            ['verz-3', 'Overlijdensrisico (NN)', 'Verzekeringen', 32.84],
            ['spaar-1', 'Buffer', 'Spaargeld', 600],
            ['spaar-2', 'Tuin + Woning', 'Spaargeld', 100],
            ['spaar-3', 'Uitjes', 'Spaargeld', 100],
            ['spaar-4', 'Timmie (kat)', 'Spaargeld', 50],
            ['spaar-5', 'Auto', 'Spaargeld', 75],
            ['spaar-6', 'Mark', 'Spaargeld', 0],
            ['spaar-7', 'Meiden', 'Spaargeld', 0],
            ['len-1', 'Hypotheek (aegon)', 'Leningen', 1317.33],
            ['len-2', 'Defam (lening)', 'Leningen', 407.61],
            ['len-3', 'Overlijdensrisico (lening)', 'Leningen', 9.06],
            ['ovv-1', 'Loterijen', 'Overige Vaste Kosten', 16],
            ['ovv-2', 'Sporten', 'Overige Vaste Kosten', 19.99],
            ['ovv-3', 'Zakgeld', 'Overige Vaste Kosten', 20],
            ['ovv-4', 'Belastingdienst', 'Overige Vaste Kosten', 0],
            ['ovv-5', 'Wasmachine + droger (coolblue)', 'Overige Vaste Kosten', 25.99],
            ['ovk-1', 'Belegging', 'Overige Kosten', 0],
            ['ovk-2', 'Zorgkosten (eigen risico)', 'Overige Kosten', 32],
            ['ovk-3', 'Abonnementen', 'Overige Kosten', 0],
            ['ovk-4', 'Openstaand', 'Overige Kosten', 0],
            ['ovk-5', 'Laptops kinderen school', 'Overige Kosten', 0],
        ];

        foreach ($items as $sort => [$key, $name, $group, $estimated]) {
            $budget = Budget::query()->create([
                'key' => $key,
                'category_id' => $categoryIds[$group],
                'name' => $name,
                'budget' => $estimated,
                'sort_order' => $sort,
            ]);

            foreach (array_keys(SparenStateService::MONTHS) as $monthId) {
                BudgetMonthValue::query()->create([
                    'budget_id' => $budget->id,
                    'month_id' => $monthId,
                    'year' => 2026,
                    'estimated' => $estimated,
                ]);
            }
        }

        $budgetIds = Budget::query()->pluck('id', 'key');

        $rules = [
            ['rule-1', 'Salaris Beekman', 'Beekman', 'ink-1'],
            ['rule-2', 'Toeslagen Kindgebonden Budget', 'VOORSCHOT KIT/Kgb', 'ink-2'],
            ['rule-3', 'Belastingdienst Teruggave', 'VOORLOPIGE TERUGGAVE', 'ink-4'],
            ['rule-4', 'Albert Heijn Boodschappen', 'AH ', 'dag-1'],
            ['rule-5', 'PLUS Holthuijsen Boodschappen', 'PLUS', 'dag-1'],
            ['rule-6', 'Kruidvat Verzorging', 'Kruidvat', 'ovk-2'],
            ['rule-7', 'HEMA Inkopen', 'HEMA', 'dag-1'],
            ['rule-8', 'GreenChoice Stroom', 'GreenChoice', 'won-1'],
            ['rule-9', 'Ennatuurlijk Warmte', 'Ennatuurlijk', 'won-2'],
            ['rule-10', 'Vitens Water & GBLT', 'Vitens', 'won-3'],
            ['rule-11', 'KPN Glasvezel', 'KPN', 'won-4'],
            ['rule-12', 'Aegon Hypotheek', 'Aegon', 'len-1'],
            ['rule-13', 'Defam Lening', 'Defam', 'len-2'],
            ['rule-14', 'InShared Verzekering', 'InShared', 'verz-2'],
            ['rule-15', 'Coolblue Witgoed', 'Coolblue', 'ovv-5'],
            ['rule-16', 'Sparen Buffer Storting', 'Oranje spaarrekening', 'spaar-1'],
        ];

        foreach ($rules as [$key, $name, $keyword, $budgetKey]) {
            $budget = Budget::query()->where('key', $budgetKey)->first();
            ImportRule::query()->create([
                'key' => $key,
                'name' => $name,
                'type' => 'description',
                'match_value' => $keyword,
                'match_field' => 'description',
                'is_active' => true,
                'category_id' => $budget->category_id,
                'budget_id' => $budget->id,
            ]);
        }

        $goals = [
            ['goal-1', 'Noodbuffer & Onvoorzien', 'NL83INGB0131342031', 'ING Oranje Spaarrekening', 4500, 2400, 600, 'emerald', 'ShieldCheck', 'spaar-1', 'Vaste maandelijkse bufferopbouw voor onvoorziene kosten'],
            ['goal-2', 'Tuin + Woning Onderhoud', 'NL12KNAB0123456789', 'Knab Spaarrekening', 2000, 1100, 100, 'indigo', 'Home', 'spaar-2', 'Onderhoud woning, schilderwerk en tuin vernieuwing'],
            ['goal-3', 'Vakantie & Uitjes', 'NL99RABO0987654321', 'Rabobank Doelsparen', 1500, 700, 100, 'amber', 'Palmtree', 'spaar-3', 'Zomervakantie en weekendjes weg met het gezin'],
            ['goal-4', 'Timmie (Dierenartskosten Kat)', 'NL44BUNQ2098765432', 'Bunq Spaarpot', 600, 400, 50, 'purple', 'Cat', 'spaar-4', 'Gezondheid, inentingen en onverwachte dierenartsbezoeken'],
            ['goal-5', 'Auto Onderhoud & Vervanging', 'NL55TRDE0011223344', 'Trade Republic Spaarrekening', 1400, 675, 75, 'cyan', 'Car', 'spaar-5', 'APK, grote beurt, bandenwissel en toekomstige inruil'],
        ];

        foreach ($goals as $row) {
            SavingsGoal::query()->create([
                'key' => $row[0],
                'name' => $row[1],
                'account_iban' => $row[2],
                'bank_name' => $row[3],
                'target_amount' => $row[4],
                'initial_amount' => $row[5],
                'monthly_contribution' => $row[6],
                'color' => $row[7],
                'icon_name' => $row[8],
                'budget_key' => $row[9],
                'notes' => $row[10],
            ]);
        }
    }
}
