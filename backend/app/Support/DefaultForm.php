<?php

namespace App\Support;

/**
 * The standard registration form attached to every new event.
 * Manager can edit/delete/add questions freely afterwards.
 */
class DefaultForm
{
    public static function questions(): array
    {
        return [
            ['question' => 'ឈ្មោះអ្នកចូលរួម', 'type' => 'text', 'required' => true, 'options' => null],
            ['question' => 'យេនឌ័រ', 'type' => 'radio', 'required' => true, 'options' => ['ប្រុស', 'ស្រី', 'ផ្សេងៗ']],
            ['question' => 'ចន្លោះអាយុ', 'type' => 'radio', 'required' => true, 'options' => ['ក្រោម ១៨ ឆ្នាំ', '១៨ – ២៤ ឆ្នាំ', '២៥ – ៣៤ ឆ្នាំ', '៣៥ ឆ្នាំឡើង']],
            ['question' => 'លេខទូរស័ព្ទ', 'type' => 'text', 'required' => false, 'options' => null],
            ['question' => 'អ៊ីម៉ែល', 'type' => 'text', 'required' => false, 'options' => null],
            ['question' => 'តើអ្នកមកពីស្ថាប័នណាមួយ?', 'type' => 'text', 'required' => false, 'options' => null],
            ['question' => 'តួនាទី', 'type' => 'radio', 'required' => true, 'options' => ['សិស្ស', 'និស្សិត', 'គ្រូបង្គៅ', 'បុគ្គលិក', 'ផ្សេងៗ']],
            ['question' => 'តើអ្នកមានពិការភាពទេ?', 'type' => 'radio', 'required' => true, 'options' => ['មាន', 'គ្មាន']],
            ['question' => 'តើអ្នកមានប្រតិកម្ម ចំណីអាហារដែរឬទេ?', 'type' => 'radio', 'required' => true, 'options' => ['មាន', 'គ្មាន']],
            ['question' => 'សូមបញ្ជាក់ចំណីអាហារដែលអ្នកប្រតិកម្ម', 'type' => 'text', 'required' => false, 'options' => null],
            ['question' => 'តើអ្នកត្រូវការជំនួយពិសេសអ្វីដែរ ឬទេ?', 'type' => 'textarea', 'required' => false, 'options' => null],
        ];
    }
}
