<?php
namespace local_stackmathgame\output;

defined('MOODLE_INTERNAL') || die();

class renderer extends \plugin_renderer_base {
    public function studio_tabs(string $active, array $caps, ?int $designid = null): string {
        $tabs = [];
        $base = new \moodle_url('/local/stackmathgame/studio.php');
        $tabs[] = new \tabobject('overview', new \moodle_url($base, ['action' => 'overview']),
            get_string('studio_tab_overview', 'local_stackmathgame'));
        if ($caps['managethemes']) {
            $params = ['action' => 'edit'];
            if ($designid) {
                $params['id'] = $designid;
            }
            $tabs[] = new \tabobject('edit', new \moodle_url($base, $params),
                get_string('studio_tab_edit', 'local_stackmathgame'));
        }
        if ($caps['manageassets']) {
            $tabs[] = new \tabobject('import', new \moodle_url($base, ['action' => 'import']),
                get_string('studio_tab_import', 'local_stackmathgame'));
        }
        return $this->tabtree($tabs, $active);
    }

    public function studio_intro(array $caps): string {
        $items = [];
        $items[] = \html_writer::tag('li', get_string('studio_hint_themes', 'local_stackmathgame'));
        $items[] = \html_writer::tag('li', get_string('studio_hint_assets', 'local_stackmathgame'));
        $items[] = \html_writer::tag('li', get_string('studio_hint_mechanics', 'local_stackmathgame'));
        $items[] = \html_writer::tag('li', get_string('studio_hint_roles', 'local_stackmathgame'));
        return \html_writer::div(
            \html_writer::tag('p', get_string('studio_intro', 'local_stackmathgame')) .
            \html_writer::tag('ul', implode('', $items)) .
            \html_writer::div(get_string('studio_capsummary', 'local_stackmathgame', (object)$caps), 'alert alert-info mt-3'),
            'mb-4'
        );
    }

    public function design_gallery(array $designs): string {
        if (!$designs) {
            return \html_writer::div(get_string('studio_nodesigns', 'local_stackmathgame'), 'alert alert-warning');
        }
        $cards = [];
        foreach ($designs as $design) {
            if (!empty($design['thumbnailurl'])) {
                $thumb = \html_writer::empty_tag('img', [
                    'src' => $design['thumbnailurl'],
                    'alt' => s($design['name']),
                    'class' => 'img-fluid rounded mb-2',
                    'style' => 'max-height:120px; object-fit:cover; width:100%;',
                ]);
            } else {
                $thumb = \html_writer::div(get_string('studio_nothumbnail', 'local_stackmathgame'), 'text-muted border rounded p-3 mb-2');
            }
            $meta = [];
            $meta[] = \html_writer::tag('div', s($design['modecomponent']), ['class' => 'text-muted small']);
            $meta[] = \html_writer::tag('div', !empty($design['isbundled']) ? get_string('studio_bundled', 'local_stackmathgame') : get_string('studio_imported', 'local_stackmathgame'), ['class' => 'small']);
            $meta[] = \html_writer::tag('div', !empty($design['isactive']) ? get_string('yes') : get_string('no'), ['class' => 'small']);
            $actions = \html_writer::link(new \moodle_url('/local/stackmathgame/studio.php', ['action' => 'edit', 'id' => $design['id']]), get_string('edit'));
            if (!empty($design['canexport'])) {
                $actions .= ' · ' . \html_writer::link(new \moodle_url('/local/stackmathgame/studio.php', ['action' => 'export', 'id' => $design['id'], 'sesskey' => sesskey()]), get_string('export', 'core'));
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
        return \html_writer::start_div('row g-3') .
            implode('', array_map(fn($card) => \html_writer::div($card, 'col-md-6 col-lg-4'), $cards)) .
            \html_writer::end_div();
    }
}
