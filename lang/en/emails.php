<?php

return [
    'contact_confirmed' => [
        'subject' => 'Your message has been received — Geneva Bengal',
        'greeting' => 'Hello :name,',
        'line_received' => "We've received your message and will get back to you as soon as possible.",
        'line_reminder' => 'Here is a reminder of your message:',
    ],

    'newsletter_confirmed' => [
        'subject' => 'Newsletter subscription confirmed — Geneva Bengal',
        'greeting' => 'Hello,',
        'line_subscribed' => "You're now subscribed to the Geneva Bengal newsletter.",
        'line_unsubscribe' => 'You can unsubscribe at any time with one click.',
        'action_unsubscribe' => 'Unsubscribe',
    ],

    'deposit_confirmed' => [
        'subject' => 'Your deposit has been received — Geneva Bengal',
        'greeting' => 'Hello :name,',
        'line_received' => "We've received your deposit of :amount :currency.",
        'line_cat' => "It's for your reservation on :cat.",
        'line_waiting_list' => 'It adds you to our waiting list.',
        'line_closing' => "We'll be in touch again very soon.",
    ],

    'deposit_unavailable' => [
        'subject' => 'Your reservation could not be confirmed — Geneva Bengal',
        'greeting' => 'Hello :name,',
        'line_taken' => 'The kitten you wanted to reserve was just reserved by someone else, moments before you.',
        'line_refunded' => 'Your TWINT payment was charged and has now been fully refunded — you will not be billed.',
        'line_not_charged' => 'Your card was not charged — the authorization has been cancelled.',
        'line_closing' => 'Feel free to take a look at our other available kittens.',
    ],
];
