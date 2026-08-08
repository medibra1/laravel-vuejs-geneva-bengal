<?php

namespace Database\Seeders;

use App\Models\FaqItem;
use App\Models\Page;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Real editorial content for the site's CMS-driven pages — always run
 * (dev/staging/prod), unlike DemoDataSeeder's Faker-generated content.
 * Without this, a fresh production deploy would go live with an empty
 * "Informations sur le Bengal" / "Adoption" nav dropdown (see
 * PublicLayout.vue) and "a-propos"/"contact" — routes/web.php's own
 * hardcoded literal routes — would 404 outright.
 *
 * Idempotent (firstOrCreate by slug / by fr question text): safe to
 * re-run in any environment, same convention as ColorSeeder.
 */
class ContentPagesSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedPages();
        $this->seedFaqItems();
    }

    private function seedPages(): void
    {
        $this->createPage(['fr' => 'À propos', 'en' => 'About'], [
            'fr' => 'Geneva Bengal est un élevage familial de chats Bengal basé à Genève, en Suisse. Depuis 2020, nous élevons des chatons en parfaite santé, avec une apparence et un comportement à vous faire rêver.',
            'en' => 'Geneva Bengal is a family-run Bengal cattery based in Geneva, Switzerland. Since 2020, we have been breeding kittens in perfect health, with a look and temperament to dream of.',
        ], [
            'fr' => "Découvrez notre élevage de chats Bengal à Genève : notre histoire, notre passion, et les témoignages de familles qui nous ont fait confiance.",
            'en' => 'Discover our Bengal cattery in Geneva: our story, our passion, and testimonials from families who trusted us.',
        ]);

        $this->createPage(['fr' => 'Contact', 'en' => 'Contact'], [
            'fr' => "Une question sur nos chatons disponibles, une envie d'adopter ou de vous inscrire sur notre liste d'attente ? Écrivez-nous, nous vous répondrons rapidement.",
            'en' => 'A question about our available kittens, or would you like to adopt or join our waiting list? Write to us, we will get back to you quickly.',
        ], [
            'fr' => "Contactez notre élevage Geneva Bengal pour toute question sur l'adoption d'un chaton Bengal à Genève.",
            'en' => 'Contact our Geneva Bengal cattery for any question about adopting a Bengal kitten in Geneva.',
        ]);

        foreach ($this->raceInfoPages() as $order => $page) {
            $this->createPage($page['title'], $page['body'], $page['meta'], 'race_info', $order);
        }

        foreach ($this->adoptionPages() as $order => $page) {
            $this->createPage($page['title'], $page['body'], $page['meta'], 'adoption', $order);
        }

        // Its own page (not a section on another one) so it shows up
        // alongside the other adoption-group pages in that nav dropdown —
        // Public\PageController::show() attaches faq_items to it by slug.
        $this->createPage(['fr' => 'FAQ', 'en' => 'FAQ'], [
            'fr' => '<p>Vous avez une question sur nos chatons ou le processus d\'adoption ? Vous trouverez peut-être déjà la réponse ci-dessous.</p>',
            'en' => '<p>Have a question about our kittens or the adoption process? You might already find the answer below.</p>',
        ], [
            'fr' => "Les réponses aux questions les plus fréquentes sur l'adoption d'un chaton Bengal chez Geneva Bengal.",
            'en' => 'Answers to the most frequently asked questions about adopting a Bengal kitten from Geneva Bengal.',
        ], 'adoption', count($this->adoptionPages()));
    }

    /**
     * @param  array{fr: string, en: string}  $title
     * @param  array{fr: string, en: string}  $body
     * @param  array{fr: string, en: string}  $metaDescription
     */
    private function createPage(array $title, array $body, array $metaDescription, ?string $menuGroup = null, int $order = 0): void
    {
        Page::firstOrCreate(['slug' => Str::slug($title['fr'])], [
            'menu_group' => $menuGroup,
            'order' => $order,
            'title' => $title,
            'body' => $body,
            'meta_title' => ['fr' => $title['fr'].' — Geneva Bengal', 'en' => $title['en'].' — Geneva Bengal'],
            'meta_description' => $metaDescription,
            'is_published' => true,
        ]);
    }

    /**
     * @return list<array{title: array{fr: string, en: string}, body: array{fr: string, en: string}, meta: array{fr: string, en: string}}>
     */
    private function raceInfoPages(): array
    {
        return [
            [
                'title' => ['fr' => 'Caractéristiques de la race', 'en' => 'Breed characteristics'],
                'body' => [
                    'fr' => "<p>Le Bengal est une race de chat domestique récente, née dans les années 1980 du croisement entre un chat domestique et un chat-léopard asiatique (Prionailurus bengalensis). Le résultat est un chat au physique spectaculaire — silhouette athlétique, musculature puissante, robe couverte de taches ou de marbrures rappelant celle d'un félin sauvage — mais au tempérament entièrement domestique.</p><p>Nos chats Bengal pèsent généralement entre 3,5 et 7 kg à l'âge adulte, les mâles étant plus imposants que les femelles. Leur poil est court, dense et d'une texture particulièrement douce, presque satinée — une caractéristique propre à la race, parfois appelée « glitter » lorsque chaque poil réfléchit la lumière.</p><p>Reconnu par les principales fédérations félines internationales (TICA, LOOF, FIFe), le Bengal se distingue aussi par sa morphologie : tête légèrement plus petite que le corps, oreilles courtes et arrondies, yeux grands et expressifs, allant du vert à l'or en passant par le noisette selon la robe.</p>",
                    'en' => '<p>The Bengal is a recent domestic cat breed, developed in the 1980s by crossing a domestic cat with an Asian leopard cat (Prionailurus bengalensis). The result is a cat with a spectacular look — an athletic build, powerful muscles, and a coat covered in spots or marbling reminiscent of a wild feline — combined with an entirely domestic temperament.</p><p>Our Bengal cats typically weigh between 3.5 and 7 kg as adults, with males generally larger than females. Their coat is short, dense, and remarkably soft — almost satin-like — a trait unique to the breed sometimes called "glitter," where each hair reflects light.</p><p>Recognised by the major international cat associations (TICA, LOOF, FIFe), the Bengal is also known for its distinctive build: a head slightly smaller than the body, short rounded ears, and large expressive eyes ranging from green to gold to hazel depending on the coat.</p>',
                ],
                'meta' => [
                    'fr' => 'Découvrez les caractéristiques physiques et l\'origine de la race Bengal : silhouette, poids, poil et morphologie.',
                    'en' => "Discover the Bengal breed's physical traits and origins: build, weight, coat, and morphology.",
                ],
            ],
            [
                'title' => ['fr' => 'Motifs et couleurs', 'en' => 'Patterns and colors'],
                'body' => [
                    'fr' => "<p>La robe du Bengal se décline en deux grands motifs : le motif tacheté (« spotted »), avec des taches rondes, en rosette ou en forme de flèche réparties sur tout le corps, et le motif marbré (« marbled »), aux volutes fluides et asymétriques évoquant le pelage d'un chat marbré sauvage. Les rosettes, considérées comme la marque de fabrique de la race, peuvent être simples, doubles ou en forme de patte d'ours selon les lignées.</p><p>Côté couleurs, on retrouve principalement le brown (fond doré à roux, taches noires ou brunes), le silver (fond blanc argenté, contraste marqué), le snow (fond crème à ivoire, hérité des lignées siamoises ou burmese, avec plusieurs variantes : seal lynx, seal mink, seal sepia) et plus rarement le charcoal, au masque facial sombre très marqué, ou le bleu.</p><p>Chaque chaton présenté sur notre site indique sa couleur et son motif dominant — n'hésitez pas à nous contacter si vous recherchez une combinaison particulière pour une prochaine portée.</p>",
                    'en' => "<p>The Bengal's coat comes in two main patterns: spotted, with round, rosetted, or arrowhead-shaped markings spread across the whole body, and marbled, with flowing, asymmetrical swirls reminiscent of a wild marbled cat's coat. Rosettes, considered the breed's signature feature, can be single, double, or paw-print shaped depending on the bloodline.</p><p>As for colors, the main ones are brown (golden to rust background with black or brown markings), silver (silvery-white background with strong contrast), snow (cream to ivory background inherited from Siamese or Burmese bloodlines, with several variants: seal lynx, seal mink, seal sepia), and more rarely charcoal, with a strikingly dark facial mask, or blue.</p><p>Every kitten listed on our site shows its dominant color and pattern — feel free to contact us if you're looking for a specific combination for an upcoming litter.</p>",
                ],
                'meta' => [
                    'fr' => 'Motifs tacheté et marbré, couleurs brown, silver, snow, charcoal : tout savoir sur la robe du chat Bengal.',
                    'en' => "Spotted and marbled patterns, brown, silver, snow, and charcoal colors: everything about the Bengal's coat.",
                ],
            ],
            [
                'title' => ['fr' => 'Personnalité du chat Bengal', 'en' => 'Bengal cat personality'],
                'body' => [
                    'fr' => "<p>Sous ses airs de petit félin sauvage, le Bengal est un chat extrêmement sociable, joueur et attaché à sa famille. Curieux et intelligent, il s'ennuie vite d'une routine sans stimulation : jouets interactifs, arbres à chat en hauteur et séances de jeu quotidiennes font partie de son quotidien idéal.</p><p>C'est aussi une race particulièrement active et athlétique — sauts impressionnants, escalade, et pour certains, une véritable passion pour l'eau (beaucoup de Bengals aiment jouer avec un robinet qui coule ou même barboter). Mieux vaut prévoir un environnement riche en occasions de dépense physique.</p><p>Malgré son énergie, le Bengal est aussi un chat très communicatif et affectueux envers ses proches, suivant volontiers son propriétaire de pièce en pièce. Bien socialisé dès le plus jeune âge — ce à quoi nous veillons particulièrement chez chacun de nos chatons — il s'adapte très bien à la vie de famille, y compris avec des enfants ou d'autres animaux.</p>",
                    'en' => "<p>Behind its wild-looking appearance, the Bengal is an extremely sociable, playful cat deeply attached to its family. Curious and intelligent, it quickly gets bored without stimulation: interactive toys, tall cat trees, and daily play sessions are part of its ideal routine.</p><p>It's also a particularly active and athletic breed — impressive jumps, climbing, and for some, a genuine fascination with water (many Bengals love playing with a running tap or even splashing about). Plan for an environment with plenty of opportunities for physical activity.</p><p>Despite its energy, the Bengal is also a very communicative and affectionate cat with its family, happily following its owner from room to room. Well socialised from a young age — something we pay particular attention to with every one of our kittens — it adapts very well to family life, including with children or other pets.</p>",
                ],
                'meta' => [
                    'fr' => 'Curieux, joueur, athlétique et attachant : découvrez le tempérament unique du chat Bengal au quotidien.',
                    'en' => "Curious, playful, athletic, and affectionate: discover the Bengal cat's unique everyday temperament.",
                ],
            ],
            [
                'title' => ['fr' => 'Alimentation du chat Bengal', 'en' => 'Bengal cat diet'],
                'body' => [
                    'fr' => "<p>Comme tout chat de race, le Bengal a besoin d'une alimentation de qualité, riche en protéines animales, pour soutenir sa musculature athlétique et son niveau d'énergie élevé. Nous privilégions une alimentation premium, sans céréales ou à faible teneur en glucides, adaptée à son métabolisme de carnivore strict.</p><p>Chaque chaton quitte notre élevage avec un carnet expliquant précisément son alimentation actuelle (marque, quantités, fréquence des repas) — nous recommandons de conserver la même alimentation les premières semaines, puis d'effectuer toute transition progressivement sur 7 à 10 jours pour éviter les troubles digestifs.</p><p>L'eau fraîche doit être disponible en permanence ; certains Bengals préfèrent boire à une fontaine à eau plutôt qu'à une gamelle classique. Comme pour toute race, l'obésité doit être surveillée : mieux vaut des portions mesurées et quelques séances de jeu actif qu'une gamelle en libre-service.</p>",
                    'en' => "<p>Like any pedigree cat, the Bengal needs a high-quality diet, rich in animal protein, to support its athletic build and high energy levels. We favour premium, grain-free or low-carbohydrate food suited to its strict-carnivore metabolism.</p><p>Every kitten leaves our cattery with a booklet detailing its current diet (brand, quantities, meal frequency) — we recommend keeping the same food for the first few weeks, then making any change gradually over 7 to 10 days to avoid digestive upset.</p><p>Fresh water should always be available; some Bengals actually prefer drinking from a water fountain rather than a regular bowl. As with any breed, weight should be monitored — measured portions and a few active play sessions are better than food left out at all times.</p>",
                ],
                'meta' => [
                    'fr' => 'Nos conseils pour bien nourrir votre chat Bengal : alimentation adaptée, transition et bonnes pratiques.',
                    'en' => "Our advice for feeding your Bengal cat well: a suitable diet, transitioning food, and best practices.",
                ],
            ],
            [
                'title' => ['fr' => 'Santé du chat Bengal', 'en' => 'Bengal cat health'],
                'body' => [
                    'fr' => "<p>La santé de nos chats est notre priorité absolue, bien avant toute considération esthétique. Tous nos reproducteurs sont testés pour les principales prédispositions génétiques connues chez le Bengal : la cardiomyopathie hypertrophique (HCM), la déficience en pyruvate kinase (PK-Def) et l'atrophie rétinienne progressive (PRA-b), par test ADN ou dépistage échocardiographique selon le cas.</p><p>Chaque chaton est vermifugé et vacciné selon le protocole en vigueur avant son départ, examiné par notre vétérinaire, identifié par puce électronique, et accompagné d'un carnet de santé complet. Un contrat d'élevage précise les garanties sanitaires applicables à l'adoption.</p><p>Comme toute race, le Bengal bénéficie d'un suivi vétérinaire régulier tout au long de sa vie — vaccins de rappel, contrôle dentaire, et vermifugation périodique. N'hésitez pas à nous contacter si vous avez des questions sur la santé d'un chaton en particulier avant votre visite.</p>",
                    'en' => "<p>Our cats' health is our absolute priority, well ahead of any aesthetic consideration. All our breeding cats are tested for the main genetic predispositions known in the Bengal breed: hypertrophic cardiomyopathy (HCM), pyruvate kinase deficiency (PK-Def), and progressive retinal atrophy (PRA-b), via DNA testing or echocardiographic screening as appropriate.</p><p>Every kitten is dewormed and vaccinated according to the current protocol before leaving, examined by our veterinarian, microchipped, and sent home with a complete health record. A breeding contract sets out the health guarantees that apply to the adoption.</p><p>Like any breed, the Bengal benefits from regular veterinary follow-up throughout its life — booster vaccines, dental checks, and periodic deworming. Feel free to contact us with any questions about a particular kitten's health before your visit.</p>",
                ],
                'meta' => [
                    'fr' => 'Dépistages génétiques, vaccination et suivi vétérinaire : la santé de nos chats Bengal est notre priorité.',
                    'en' => "Genetic screening, vaccination, and veterinary follow-up: our Bengal cats' health is our top priority.",
                ],
            ],
        ];
    }

    /**
     * @return list<array{title: array{fr: string, en: string}, body: array{fr: string, en: string}, meta: array{fr: string, en: string}}>
     */
    private function adoptionPages(): array
    {
        return [
            [
                'title' => ['fr' => 'Étapes pour adopter un chaton Bengal', 'en' => 'Steps to adopt a Bengal kitten'],
                'body' => [
                    'fr' => "<p><strong>1. Prise de contact</strong> : parcourez nos chatons disponibles ou inscrivez-vous sur notre liste d'attente si aucun chaton ne correspond encore à vos critères, puis contactez-nous via le formulaire pour nous parler de votre projet.</p><p><strong>2. Échange et réservation</strong> : nous discutons ensemble de vos attentes (couleur, tempérament, présence d'autres animaux...) et, si un chaton vous correspond, vous pouvez le réserver via un acompte, qui bloque le chaton et l'inscrit hors liste des disponibles jusqu'à son départ.</p><p><strong>3. Suivi et rencontre</strong> : nous restons en contact durant les semaines qui précèdent le départ du chaton (photos, vidéos, nouvelles), et vous êtes bienvenus à venir le rencontrer à l'élevage avant l'adoption définitive.</p><p><strong>4. Départ du chaton</strong> : votre chaton part chez vous entre 12 et 16 semaines, jamais avant, sevré, vacciné, vermifugé, identifié, avec son carnet de santé, son contrat d'élevage et un kit de démarrage.</p>",
                    'en' => "<p><strong>1. Getting in touch</strong>: browse our available kittens or join our waiting list if none currently matches what you're looking for, then contact us via the form to tell us about your project.</p><p><strong>2. Discussion and reservation</strong>: we talk together about your expectations (color, temperament, other pets at home...) and, if a kitten suits you, you can reserve it with a deposit, which holds the kitten and takes it off the available list until it goes home.</p><p><strong>3. Follow-up and visit</strong>: we stay in touch during the weeks before the kitten leaves (photos, videos, updates), and you're welcome to come meet it at the cattery before the final adoption.</p><p><strong>4. The kitten goes home</strong>: your kitten leaves for its new home between 12 and 16 weeks old, never earlier, weaned, vaccinated, dewormed, microchipped, with its health record, breeding contract, and a starter kit.</p>",
                ],
                'meta' => [
                    'fr' => 'Les 4 étapes pour adopter un chaton Bengal chez Geneva Bengal, de la prise de contact au jour du départ.',
                    'en' => 'The 4 steps to adopting a Bengal kitten from Geneva Bengal, from first contact to the day it goes home.',
                ],
            ],
            [
                'title' => ["fr" => "Introduction d'un nouveau chaton à la maison", 'en' => 'Introducing a new kitten at home'],
                'body' => [
                    'fr' => "<p>Les premiers jours sont déterminants pour que votre chaton se sente en confiance dans son nouvel environnement. Préparez une pièce calme avec sa litière, sa gamelle, son eau et un espace pour se cacher — laissez-le explorer à son rythme plutôt que de forcer le contact.</p><p>Si vous avez déjà un chat ou un chien à la maison, prévoyez une introduction progressive sur plusieurs jours : séparation initiale, échange d'odeurs (couvertures, jouets), puis rencontres courtes et supervisées. La patience est essentielle — une cohabitation réussie se construit rarement en un jour.</p><p>Gardez la même alimentation que celle donnée à l'élevage durant les premiers jours (voir notre page Alimentation), et prévoyez un rendez-vous chez votre vétérinaire dans les jours suivant l'adoption pour un premier contrôle et le suivi du protocole vaccinal.</p>",
                    'en' => "<p>The first few days are crucial for your kitten to feel confident in its new environment. Prepare a quiet room with its litter box, food and water bowls, and a hiding spot — let it explore at its own pace rather than forcing contact.</p><p>If you already have a cat or dog at home, plan a gradual introduction over several days: initial separation, scent swapping (blankets, toys), then short, supervised meetings. Patience is essential — a successful cohabitation is rarely built in a single day.</p><p>Keep feeding the same food given at the cattery for the first few days (see our Diet page), and schedule a visit to your veterinarian in the days following the adoption for a first check-up and to continue the vaccination schedule.</p>",
                ],
                'meta' => [
                    'fr' => "Nos conseils pour bien accueillir votre nouveau chaton Bengal à la maison, seul ou avec d'autres animaux.",
                    'en' => 'Our tips for welcoming your new Bengal kitten home, whether alone or alongside other pets.',
                ],
            ],
            [
                'title' => ['fr' => 'Prix de nos chats Bengal', 'en' => 'Prices of our Bengal cats'],
                'body' => [
                    'fr' => "<p>Le prix d'un chaton Bengal varie selon plusieurs critères : sa couleur et son motif, sa lignée et son pedigree, sa destination (compagnie ou reproduction/exposition), ainsi que son statut de stérilisation. Un chaton destiné uniquement à la compagnie, stérilisé avant son départ, est généralement proposé à un tarif plus accessible qu'un sujet destiné à l'élevage ou à l'exposition.</p><p>Le prix affiché sur la fiche de chaque chaton disponible correspond au tarif complet, incluant le premier vaccin, la vermifugation, l'identification par puce électronique, le contrat d'élevage et un kit de départ. Aucun frais caché ne s'ajoute par la suite.</p><p>Une réservation se fait via le versement d'un acompte, déduit du prix total au moment du départ du chaton. Pour toute question sur le prix d'un chaton en particulier ou sur nos disponibilités à venir, n'hésitez pas à nous contacter.</p>",
                    'en' => "<p>The price of a Bengal kitten depends on several factors: its color and pattern, its bloodline and pedigree, its intended purpose (companion or breeding/show), and its neuter/spay status. A companion-only kitten, neutered before it leaves, is generally offered at a more accessible price than one intended for breeding or showing.</p><p>The price shown on each available kitten's page is the full price, including the first vaccination, deworming, microchip identification, the breeding contract, and a starter kit. No hidden fees are added afterwards.</p><p>A reservation is made with a deposit, which is deducted from the total price when the kitten goes home. For any question about a specific kitten's price or our upcoming availability, feel free to contact us.</p>",
                ],
                'meta' => [
                    'fr' => "Comprendre le prix d'un chaton Bengal chez Geneva Bengal : critères, tarif complet et modalités de réservation.",
                    'en' => "Understanding the price of a Bengal kitten from Geneva Bengal: pricing factors, what's included, and reservations.",
                ],
            ],
        ];
    }

    private function seedFaqItems(): void
    {
        $existingQuestions = FaqItem::query()->get()
            ->map(fn (FaqItem $item) => $item->getTranslation('question', 'fr'))
            ->all();

        $faqs = [
            [
                'question' => ['fr' => "Quel est le prix d'un chaton Bengal ?", 'en' => 'What is the price of a Bengal kitten?'],
                'answer' => [
                    'fr' => "Le prix varie selon la couleur, le motif, la lignée et la destination du chaton (compagnie ou reproduction). Retrouvez le détail sur notre page Prix, ou consultez directement la fiche du chaton qui vous intéresse pour son tarif exact.",
                    'en' => "The price varies depending on the kitten's color, pattern, bloodline, and intended purpose (companion or breeding). See our Pricing page for details, or check the specific kitten's page for its exact price.",
                ],
            ],
            [
                'question' => ["fr" => "Livrez-vous à l'international ?", 'en' => 'Do you ship internationally?'],
                'answer' => [
                    'fr' => "Nous privilégions une remise en main propre à l'élevage, à Genève, ou un point de rencontre à convenir en Suisse. Une livraison internationale peut être envisagée au cas par cas, avec les formalités sanitaires et administratives à la charge de l'adoptant — contactez-nous pour en discuter.",
                    'en' => 'We prefer handing the kitten over in person at our cattery in Geneva, or at an agreed meeting point in Switzerland. International delivery can be considered case by case, with health and administrative formalities to be handled by the adopter — contact us to discuss it.',
                ],
            ],
            [
                'question' => ['fr' => "Quel est le délai d'attente pour adopter ?", 'en' => 'What is the waiting time to adopt?'],
                'answer' => [
                    'fr' => "Cela dépend des disponibilités du moment et de vos critères (couleur, motif, sexe...). Si aucun chaton disponible ne vous correspond, nous vous inscrivons sur notre liste d'attente pour une prochaine portée — le délai est alors généralement de quelques semaines à quelques mois.",
                    'en' => "It depends on current availability and your criteria (color, pattern, sex...). If no available kitten matches what you're looking for, we'll add you to our waiting list for an upcoming litter — the wait is usually a few weeks to a few months.",
                ],
            ],
            [
                'question' => ['fr' => 'Les chatons sont-ils vaccinés et vermifugés ?', 'en' => 'Are the kittens vaccinated and dewormed?'],
                'answer' => [
                    'fr' => "Oui, chaque chaton quitte notre élevage vacciné selon le protocole en vigueur, vermifugé, identifié par puce électronique et examiné par notre vétérinaire, avec un carnet de santé complet remis à l'adoption.",
                    'en' => 'Yes, every kitten leaves our cattery vaccinated according to the current protocol, dewormed, microchipped, and examined by our veterinarian, with a complete health record provided at adoption.',
                ],
            ],
        ];

        foreach ($faqs as $order => $item) {
            if (in_array($item['question']['fr'], $existingQuestions, true)) {
                continue;
            }

            FaqItem::create([
                'question' => $item['question'],
                'answer' => $item['answer'],
                'order' => $order,
            ]);
        }
    }
}
