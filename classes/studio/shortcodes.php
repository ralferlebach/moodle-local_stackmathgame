<?php
namespace local_stackmathgame\studio;

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal shortcode parser for studio-authored narrative text.
 */
class shortcodes {
    public static function render(string $text, array $vars = []): string {
        foreach ($vars as $key => $value) {
            $text = str_replace('{{' . $key . '}}', (string)$value, $text);
        }
        return $text;
    }
}
