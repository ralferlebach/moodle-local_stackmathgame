<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Renderer for local_stackmathgame studio pages.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_stackmathgame\output;

/**
 * Studio renderer for STACK Math Game.
 *
 * @package    local_stackmathgame
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class renderer extends \plugin_renderer_base {
    /**
     * Render the studio tab navigation.
     *
     * @param string   $active   The active tab key.
     * @param array    $caps     Capability flags array.
     * @param int|null $designid Optional design ID for edit tab.
     * @return string HTML tab navigation.
     */
    public function studio_tabs(string $active, array $caps, ?int $designid = null): string {
        $tabs = [];
        $base = new \moodle_url('/local/stackmathgame/studio.php');
        $tabs[] = new \tabobject(
            'overview',
            new \moodle_url($base, ['action' => 'overview']),
            get_string('studio_tab_overview', 'local_stackmathgame')
        );
        if (!empty($caps['managethemes'])) {
            $params = ['action' => 'edit'];
            if ($designid) {
                $params['id'] = $designid;
            }
            $tabs[] = new \tabobject(
                'edit',
                new \moodle_url($base, $params),
                get_string('studio_tab_edit', 'local_stackmathgame')
            );
        }
        if (!empty($caps['manageassets'])) {
            $tabs[] = new \tabobject(
                'import',
                new \moodle_url($base, ['action' => 'import']),
                get_string('studio_tab_import', 'local_stackmathgame')
            );
        }
        return $this->tabtree($tabs, $active);
    }

    /**
     * Render the studio overview introduction block.
     *
     * @param array $caps Capability flags array.
     * @return string HTML introduction block.
     */
    public function studio_intro(array $caps): string {
        $items   = [];
        $items[] = \html_writer::tag('li', get_string('studio_hint_themes', 'local_stackmathgame'));
        $items[] = \html_writer::tag('li', get_string('studio_hint_assets', 'local_stackmathgame'));
        $items[] = \html_writer::tag('li', get_string('studio_hint_mechanics', 'local_stackmathgame'));
        $items[] = \html_writer::tag('li', get_string('studio_hint_roles', 'local_stackmathgame'));
        $capobj  = (object)array_map(
            static fn($v): string => $v ? get_string('yes') : get_string('no'),
            $caps
        );
        return \html_writer::div(
            \html_writer::tag('p', get_string('studio_intro', 'local_stackmathgame')) .
            \html_writer::tag('ul', implode('', $items)) .
            \html_writer::div(
                get_string('studio_capsummary', 'local_stackmathgame', $capobj),
                'alert alert-info mt-3'
            ),
            'mb-4'
        );
    }

    /**
     * Render a grid gallery of available designs.
     *
     * @param array $designs Array of design export arrays.
     * @return string HTML gallery.
     */
    public function design_gallery(array $designs): string {
        if (!$designs) {
            return \html_writer::div(
                get_string('studio_nodesigns', 'local_stackmathgame'),
                'alert alert-warning'
            );
        }
        $cards = [];
        foreach ($designs as $design) {
            if (!empty($design['thumbnailurl'])) {
                $thumb = \html_writer::empty_tag('img', [
                    'src'   => $design['thumbnailurl'],
                    'alt'   => s($design['name']),
                    'class' => 'img-fluid rounded mb-2',
                    'style' => 'max-height:120px; object-fit:cover; width:100%;',
                ]);
            } else {
                $thumb = \html_writer::div(
                    get_string('studio_nothumbnail', 'local_stackmathgame'),
                    'text-muted border rounded p-3 mb-2'
                );
            }
            $bundledlabel = !empty($design['isbundled'])
                ? get_string('studio_bundled', 'local_stackmathgame')
                : get_string('studio_imported', 'local_stackmathgame');
            $activelabel  = !empty($design['isactive']) ? get_string('yes') : get_string('no');
            $meta   = [];
            $meta[] = \html_writer::tag('div', s($design['modecomponent']), ['class' => 'text-muted small']);
            $meta[] = \html_writer::tag('div', $bundledlabel, ['class' => 'small']);
            $meta[] = \html_writer::tag('div', $activelabel, ['class' => 'small']);
            $editurl   = new \moodle_url('/local/stackmathgame/studio.php', [
                'action' => 'edit',
                'id'     => $design['id'],
            ]);
            $exporturl = new \moodle_url('/local/stackmathgame/studio.php', [
                'action'  => 'export',
                'id'      => $design['id'],
                'sesskey' => sesskey(),
            ]);
            $actions = \html_writer::link($editurl, get_string('edit'));
            if (!empty($design['canexport'])) {
                $exportlabel = get_string('exportdesign', 'local_stackmathgame');
                $actions    .= ' &middot; ' . \html_writer::link($exporturl, $exportlabel);
            }
            $cards[] = \html_writer::div(
                $thumb .
                \html_writer::tag('h4', s($design['name']), ['class' => 'h5 mb-1']) .
                implode('', $meta) .
                \html_writer::tag('p', s($design['description'] ?: ''), ['class' => 'mt-2 mb-2']) .
                \html_writer::div($actions, 'small'),
                'card p-3 h-100'
            );
        }
        $cols = array_map(
            static fn(string $card): string => \html_writer::div($card, 'col-md-6 col-lg-4'),
            $cards
        );
        return \html_writer::start_div('row g-3') .
               implode('', $cols) .
               \html_writer::end_div();
    }
}
