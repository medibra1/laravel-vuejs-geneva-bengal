<?php

return [
    'contact_confirmed' => [
        'subject' => 'Votre message a bien été reçu — Geneva Bengal',
        'greeting' => 'Bonjour :name,',
        'line_received' => 'Nous avons bien reçu votre message et nous vous répondrons dans les plus brefs délais.',
        'line_reminder' => 'Voici un rappel de votre message :',
    ],

    'newsletter_confirmed' => [
        'subject' => "Confirmation d'inscription à la newsletter — Geneva Bengal",
        'greeting' => 'Bonjour,',
        'line_subscribed' => 'Vous êtes désormais inscrit(e) à la newsletter Geneva Bengal.',
        'line_unsubscribe' => 'Vous pouvez vous désinscrire à tout moment en un clic.',
        'action_unsubscribe' => 'Se désabonner',
    ],

    'deposit_confirmed' => [
        'subject' => 'Votre acompte a bien été reçu — Geneva Bengal',
        'greeting' => 'Bonjour :name,',
        'line_received' => 'Nous avons bien reçu votre acompte de :amount :currency.',
        'line_cat' => 'Il concerne votre réservation pour :cat.',
        'line_waiting_list' => "Il vous inscrit sur notre liste d'attente.",
        'line_closing' => 'Nous reviendrons vers vous très prochainement.',
    ],

    'deposit_unavailable' => [
        'subject' => "Votre réservation n'a pas pu être confirmée — Geneva Bengal",
        'greeting' => 'Bonjour :name,',
        'line_taken' => "Le chaton que vous souhaitiez réserver vient d'être réservé par quelqu'un d'autre, quelques instants avant vous.",
        'line_refunded' => 'Votre paiement TWINT a été débité puis intégralement remboursé — vous ne serez pas facturé.',
        'line_not_charged' => "Votre carte n'a pas été débitée — l'autorisation a été annulée.",
        'line_closing' => 'N\'hésitez pas à consulter nos autres chatons disponibles.',
    ],
];
