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
 * Fantasy RPG mode: the question becomes a scene.
 *
 * The layout follows the original "Digital Mentoring" fantasy game: the question text sits in a
 * parchment panel above a painted scene; the answer field, STACK's validation and the check
 * control float in the scene; the elf faces a monster that disappears when the answer is right;
 * fairies and mana are counted in a circle and a square at the bottom; wooden arrows lead back
 * and on. A welcome or briefing page is shown as the starting meadow with two signposts.
 *
 * Every position is a percentage of a stage with a fixed aspect ratio. The original placed its
 * elements in pixels against a background that scaled with the window, so on a wide screen the
 * scene grew taller than the viewport and the bottom row - counters and arrows - fell off it.
 * Here the stage is capped by the viewport height as well as by the column width, so the whole
 * scene is always visible and the elements keep their places at any width.
 *
 * Artwork is addressed by key from the design's resolved asset map, with the sprite geometry the
 * package declares. The mode never builds a path and never loads anything from elsewhere.
 *
 * Based on alquiz-fantasy-bg-ver3.js
 * (c) 2022 Malte Neugebauer, Hochschule Bochum (MIT Licence).
 * Adapted for the local_stackmathgame subplugin AMD module contract.
 *
 * @module     stackmathgamemode_rpg/game
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['local_stackmathgame/game_core', 'core_filters/events'], function(GameCore, FilterEvents) {

    'use strict';

    /** Mana gained on the first solve of a scene, by scene type. */
    var MANA_GAIN = {
        boss: 30,
        miniboss: 15,
        challenge: 10,
        reward: 10,
        transition: 5,
        instruction: 0,
        outro: 5
    };

    /** Mana ceiling and starting value (the original's defaults). */
    var MANA_MAX = 100;
    var MANA_START = 20;

    /** Scene types shown as the starting meadow rather than as a fight. */
    var MEADOW_TYPES = ['instruction', 'transition', 'outro'];

    /**
     * Build a slot-keyed map from the question map.
     *
     * @param {Array} questionmap Question map rows from get_quiz_config.
     * @returns {Object} Map of slot number (string) to its parsed direction card.
     */
    function buildSlotMap(questionmap) {
        var map = {};
        (questionmap || []).forEach(function(row) {
            var cfg = null;
            try {
                cfg = row.configjson ? JSON.parse(row.configjson) : null;
            } catch (e) {
                cfg = null;
            }
            map[String(row.slotnumber)] = cfg || GameCore.defaultConfig();
        });
        return map;
    }

    /**
     * Index the resolved assets by key, with their sprite geometry where the package gives one.
     *
     * @param {Object} gameState The state handed to init().
     * @returns {Object} Map of key to {url, frames, framewidth, frameheight, frameduration}.
     */
    function indexAssets(gameState) {
        var map = {};
        var urls = gameState.assets || {};
        Object.keys(urls).forEach(function(key) {
            if (urls[key]) {
                map[key] = {key: key, url: urls[key], frames: 1};
            }
        });
        (gameState.assetlist || []).forEach(function(asset) {
            if (asset && asset.key && asset.url) {
                map[asset.key] = asset;
            }
        });
        return map;
    }

    /**
     * Rebuild the counters from the server's record of solved slots.
     *
     * Derived, not accumulated: a reload, a second tab or a duplicate submission all arrive at the
     * same numbers, because there is nothing to double-count.
     *
     * @param {Object} gameState The state handed to init().
     * @param {Object} slotMap The slot configuration by slot number.
     * @returns {{mana: number, fairies: number}} The derived counters.
     */
    function computeScore(gameState, slotMap) {
        var score = {mana: MANA_START, fairies: 0};
        var profile = gameState.profile || {};
        var progress = {};
        try {
            progress = JSON.parse(profile.progressjson || '{}') || {};
        } catch (e) {
            progress = {};
        }
        var slots = progress.slots || {};
        Object.keys(slots).forEach(function(slot) {
            if (!slots[slot] || !slots[slot].solved) {
                return;
            }
            var cfg = slotMap[String(slot)] || {};
            var type = (cfg.scene && cfg.scene.type) ? cfg.scene.type : 'challenge';
            var gain = MANA_GAIN[type] !== undefined ? MANA_GAIN[type] : 10;
            score.mana = Math.min(MANA_MAX, score.mana + gain);
            score.fairies++;
        });
        return score;
    }


    /** Prefix of every counter cache entry in sessionStorage. */
    var CACHE_PREFIX = 'smg_rpg_score:';

    /**
     * Build the storage key for the counters of one player in one campaign.
     *
     * Scoped by user as well as by campaign. sessionStorage belongs to the browser tab, not to
     * the Moodle session: after a logout and a login as somebody else in the same tab the
     * previous player's counters were still there under the same key, and were shown as the new
     * player's (re-audit N07).
     *
     * @param {Object} config The engine configuration (userid, labelid, quizid).
     * @returns {string} The key.
     */
    function cacheKeyFor(config) {
        var cfg = config || {};
        var campaign = cfg.labelid ? 'l' + cfg.labelid : 'q' + (cfg.quizid || 0);
        return CACHE_PREFIX + 'u' + (parseInt(cfg.userid || 0, 10) || 0) + ':' + campaign;
    }

    /**
     * Drop counter caches that belong to another player.
     *
     * @param {number} userid The current player.
     * @returns {void}
     */
    function forgetOtherPlayers(userid) {
        try {
            var own = CACHE_PREFIX + 'u' + (parseInt(userid || 0, 10) || 0) + ':';
            var stale = [];
            for (var i = 0; i < sessionStorage.length; i++) {
                var key = sessionStorage.key(i);
                if (key && key.indexOf(CACHE_PREFIX) === 0 && key.indexOf(own) !== 0) {
                    stale.push(key);
                }
            }
            stale.forEach(function(key) {
                sessionStorage.removeItem(key);
            });
        } catch (e) {
            // Storage unavailable: there is nothing cached to leak either.
            return;
        }
    }

    /**
     * Choose the counters to show: the server's whenever it has spoken.
     *
     * The counters are derived from the profile the server sends, and that is the record. A
     * cached value is only a stand-in for a page that received no profile at all; it used to win
     * whenever it was higher, so a stale or foreign cache could show more fairies than the
     * player had ever freed (re-audit N07).
     *
     * @param {Object|null} server Counters derived from the server profile, null when none came.
     * @param {Object|null} cached Counters from the cache, null when none.
     * @returns {{mana: number, fairies: number}} The counters to show.
     */
    function chooseScore(server, cached) {
        if (server) {
            return server;
        }
        return cached || {mana: MANA_START, fairies: 0};
    }

    /**
     * Read the cached counters for this campaign.
     *
     * @param {string} key The campaign-scoped storage key.
     * @returns {Object|null} The cached counters, or null.
     */
    function readCachedScore(key) {
        try {
            var parsed = JSON.parse(sessionStorage.getItem(key) || 'null');
            if (parsed && typeof parsed.mana === 'number') {
                return {mana: parsed.mana, fairies: parsed.fairies || 0};
            }
        } catch (e) {
            // A malformed or unavailable cache means recomputing from the profile, which is the
            // source of truth anyway.
            return null;
        }
        return null;
    }

    /**
     * Cache the counters for this campaign.
     *
     * @param {string} key The campaign-scoped storage key.
     * @param {Object} score The counters.
     * @returns {void}
     */
    function writeCachedScore(key, score) {
        try {
            sessionStorage.setItem(key, JSON.stringify(score));
        } catch (e) {
            // Private browsing or a full quota: the counters recompute on the next page load.
            return;
        }
    }

    /**
     * Inject the stage styles once.
     *
     * @returns {void}
     */
    function injectStyles() {
        if (document.getElementById('smg-rpg-styles')) {
            return;
        }
        var css = [
            // Stage: fixed aspect, capped by the column and by the viewport height, so the bottom
            // row stays on screen however wide the window is. This is the fix for the original's
            // wide-screen bug, where a scene scaled by width alone outgrew the viewport.
            '.smg-rpg-stage{position:relative;isolation:isolate;width:100%;margin:0 auto;aspect-ratio:3/2;',
            'max-width:min(1200px,calc((100vh - 210px) * 1.5));overflow:hidden;border-radius:4px;',
            'background:#23452f center/cover no-repeat;container-type:inline-size;}',
            // Below Boost's drawer toggles (z-index 2): at the same level the panel, coming later in
            // the page, covered "Open block drawer" on a phone, and the quiz navigation could not
            // be opened at all. The stage isolates its own layers, so its figures stay inside it.
            '.smg-rpg-panel{position:relative;z-index:1;margin:0 auto;',
            'max-width:min(1200px,calc((100vh - 210px) * 1.5));background:#f7ebd8;',
            'border-radius:0 0 18px 18px;box-shadow:0 4px 12px rgba(0,0,0,.28);padding:14px 26px;',
            'font-size:1.15rem;}',
            '.smg-rpg-panel:empty{display:none;}',
            // Moodle and STACK style the formulation as a tinted box; in the panel it is text on
            // parchment. Three classes deep so it outranks ".que.stack .formulation".
            '.que .smg-rpg-panel .formulation{background:transparent!important;border:0;padding:0;',
            'margin:0;color:#222;}',
            // The question's own check button: the scene has its own, next to the answer.
            '.que .smg-rpg-panel .im-controls,.smg-rpg-panel .im-controls,',
            // The question's own submit controls - not the game's check, which is a submit
            // control too, so that input surfaces recognise it as a check.
            '.smg-rpg-panel input[type=submit]:not(.smg-action-check),',
            '.smg-rpg-panel button[type=submit]:not(.smg-action-check){display:none!important;}',
            '.que .smg-rpg-panel .formulation h1,.que .smg-rpg-panel .formulation h2,',
            '.que .smg-rpg-panel .formulation h3{font-size:1.3rem;margin:0 0 .3rem;}',
            // Question, answer, check and feedback form one block in the panel (issue #15). In the
            // scene they were clipped: a 27% column inside an overflow:hidden stage has no room for
            // an editor toolbar or a multi-line equivalence chain, and STACK's feedback had nowhere
            // to go at all.
            '.smg-rpg-actions{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.6rem;}',
            '.smg-rpg-actions:empty,.smg-rpg-actions[hidden]{display:none;}',
            '.smg-rpg-feedback:empty{display:none;}',
            '.smg-rpg-feedback{margin-top:.6rem;}',
            '.smg-rpg-feedback .outcome{margin:0;}',
            // Characters and props.
            '.smg-rpg-sprite{position:absolute;overflow:hidden;z-index:2;}',
            '.smg-rpg-sprite .smg-rpg-strip{height:100%;background-repeat:no-repeat;',
            'background-size:100% 100%;will-change:transform;}',
            '@keyframes smg-rpg-play{to{transform:translateX(-100%);}}',
            '.smg-rpg-player{left:3.5%;bottom:21%;height:25%;',
            'transition:left 1.2s ease-in-out,bottom 1.2s ease-in-out;}',
            '.smg-rpg-player.is-center{left:47%;bottom:22%;}',
            '.smg-rpg-meadow .smg-rpg-player{left:45%;bottom:17%;height:22%;}',
            '.smg-rpg-enemy{right:3%;bottom:24%;height:36%;transition:opacity .8s;}',
            '.smg-rpg-enemy.is-gone{opacity:0;pointer-events:none;}',
            '.smg-rpg-enemy.is-golem{right:4%;bottom:18%;height:48%;}',
            '.smg-rpg-tag{position:absolute;left:39%;top:34%;z-index:3;background:rgba(236,236,236,.9);',
            'border-radius:.5cqw;padding:.4cqw .6cqw;font-size:1.4cqw;color:#333;}',
            '.smg-rpg-prop{position:absolute;background:center/contain no-repeat;z-index:1;}',
            '.smg-rpg-sign-info{left:7%;bottom:24%;height:26%;aspect-ratio:1785/2112;}',
            '.smg-rpg-sign-right{right:6%;bottom:24%;height:28%;aspect-ratio:1440/1869;}',
            // The wisp: a blue glow, drawn rather than loaded.
            '.smg-rpg-wisp{position:absolute;width:3.4%;aspect-ratio:1;border-radius:50%;z-index:3;',
            'background:radial-gradient(circle,#fff 0,#dbe9ff 28%,rgba(120,165,255,.55) 52%,',
            'rgba(120,165,255,0) 72%);animation:smg-rpg-float 3.2s ease-in-out infinite;',
            'left:54%;top:20%;}',
            '.smg-rpg-meadow .smg-rpg-wisp{left:50.5%;top:54%;}',
            '@keyframes smg-rpg-float{50%{transform:translateY(-.8cqw);}}',
            '.smg-rpg-alert{position:absolute;left:50.3%;top:48%;z-index:3;color:#d11;',
            'font:900 2.2cqw/1 Arial,Helvetica,sans-serif;letter-spacing:-.1cqw;}',
            // Counters: fairies in a circle, mana in a square, both ringed with dashes.
            '.smg-rpg-hud{position:absolute;left:50%;bottom:2%;transform:translateX(-50%);z-index:3;',
            'display:flex;gap:4.5cqw;align-items:center;}',
            '.smg-rpg-counter{width:9cqw;aspect-ratio:1;display:grid;place-items:center;',
            'background:#3a62c9;color:#b8cdf7;font:700 6.6cqw/1 Georgia,"Times New Roman",serif;',
            'outline:.25cqw dashed rgba(170,190,215,.7);outline-offset:1.3cqw;}',
            '.smg-rpg-counter.is-fairies{border-radius:50%;}',
            '.smg-rpg-counter.is-mana{position:relative;}',
            '.smg-rpg-counter.is-mana::after{content:"";position:absolute;inset:-.6cqw;',
            'border:.25cqw dashed rgba(170,190,215,.7);}',
            '.smg-rpg-stagecount{position:absolute;left:50%;bottom:16%;transform:translateX(-50%);',
            'z-index:3;color:#fff;font-size:1.5cqw;text-shadow:0 1px 3px #000;}',
            // Wooden arrows: back on the left, on to the right; grey until the way is open.
            '.smg-rpg-arrow{position:absolute;bottom:8%;width:13%;aspect-ratio:1500/800;z-index:3;',
            'background:center/contain no-repeat;display:block;}',
            '.smg-rpg-arrow.is-back{left:3%;transform:scaleX(-1);}',
            '.smg-rpg-arrow.is-next{right:3%;}',
            '.smg-rpg-arrow.is-locked,.smg-rpg-sign-right.is-locked{filter:grayscale(1) brightness(1.35);}',
            '.smg-rpg-arrow:focus-visible,.smg-rpg-sign-right:focus-visible{outline:3px solid #fff;',
            'outline-offset:3px;}',
            // What each control does, written out: an arrow alone did not say whether it meant
            // back, on or finish, nor why it was grey.
            '.smg-rpg-navcaption{position:absolute;left:50%;top:100%;transform:translateX(-50%);',
            'margin-top:.3cqw;white-space:nowrap;color:#fff;font-size:max(11px,1.35cqw);line-height:1.2;',
            'text-shadow:0 1px 3px #000,0 0 2px #000;text-align:center;pointer-events:none;}',
            // Aligned to the scene's outer edge rather than centred under the arrow, so a caption
            // longer than its arrow stays inside the scene on a narrow screen. The back arrow is
            // mirrored, so its "right" is the visual left, and the text is mirrored back.
            '.smg-rpg-arrow.is-next .smg-rpg-navcaption{left:auto;right:0;transform:none;text-align:right;}',
            '.smg-rpg-arrow.is-back .smg-rpg-navcaption{left:auto;right:0;transform:scaleX(-1);text-align:left;}',
            '.smg-rpg-navcaption small{display:block;font-size:.85em;opacity:.9;}',
            '.smg-rpg-sign-right .smg-rpg-navcaption{top:auto;bottom:100%;margin:0 0 .3cqw;}',
            '.smg-rpg-arrow.is-locked,.smg-rpg-sign-right.is-locked{cursor:not-allowed;}',
            // Artwork that failed to load, said as such - a placeholder design shows its labels on
            // purpose; a broken package shows this.
            '.smg-rpg-assetwarning{position:absolute;right:1%;top:1%;z-index:5;background:#fff3cd;',
            'color:#664d03;border-radius:.4cqw;padding:.3cqw .6cqw;font-size:max(12px,1.2cqw);}',
            // Narrative line, over the top of the scene.
            '.smg-rpg-bubble{position:absolute;left:50%;top:3%;transform:translateX(-50%);z-index:4;',
            'max-width:70%;background:#f7ebd8;border-radius:1.4cqw;padding:1cqw 1.8cqw;',
            'font-size:1.6cqw;box-shadow:0 2px 8px rgba(0,0,0,.3);}',
            '.smg-rpg-bubble:empty{display:none;}',
            // The question's own chrome is replaced by the scene.
            // The question itself is emptied into the scene; what remains of it - the number box,
            // Moodle's general feedback - is not part of the game view.
            '.smg-rpg-active .que{display:none;}',
            // Page chrome, as in the original: the scene sits directly under the site bar, so it
            // fits the screen instead of starting below the fold.
            '.smg-rpg-active #page-header,.smg-rpg-active .activity-header,',
            '.smg-rpg-active .tertiary-navigation{display:none;}',
            // Boost caps the course column at about 830px. The original used the full width, and
            // a scene is the one page where the extra width is the point; fitStage() still caps it
            // by height and at 1200px.
            'body.smg-rpg-active #page.drawers #topofscroll.main-inner,',
            'body.smg-rpg-active.limitedwidth #page.drawers .main-inner{max-width:none!important;}',
            // Moodle's own page buttons: the wooden arrows are the navigation here, and the answer
            // is already saved when it is checked.
            '.smg-rpg-active #responseform .submitbtns,.smg-rpg-active .mod_quiz-next-nav,',
            '.smg-rpg-active .mod_quiz-prev-nav{display:none!important;}',
            '.smg-rpg-active .smg-runtime-actions{display:none;}'
        ].join('');
        var style = document.createElement('style');
        style.id = 'smg-rpg-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }

    /**
     * Create an animated sprite from a sheet.
     *
     * The sheet is a single row of frames. A strip as wide as all frames slides through the
     * visible window in whole-frame steps; a still image is simply a strip of one frame.
     *
     * @param {Object|undefined} asset The asset, with its sprite geometry.
     * @param {string} className Classes for the sprite window.
     * @param {number} fallbackAspect Width/height of one frame when the package declares none.
     * @returns {Element} The sprite window.
     */
    function makeSprite(asset, className, fallbackAspect) {
        var win = document.createElement('div');
        win.className = 'smg-rpg-sprite ' + className;
        var strip = document.createElement('div');
        strip.className = 'smg-rpg-strip';
        win.appendChild(strip);
        setSprite(win, asset, fallbackAspect, true);
        return win;
    }

    /**
     * Point a sprite window at another sheet.
     *
     * @param {Element} win The sprite window from makeSprite().
     * @param {Object|undefined} asset The sheet to show.
     * @param {number} fallbackAspect Width/height of one frame when the package declares none.
     * @param {boolean} loop Whether to repeat the animation.
     * @returns {number} How long one run of the animation takes, in milliseconds.
     */
    function setSprite(win, asset, fallbackAspect, loop) {
        var strip = win.firstChild;
        if (!asset) {
            win.style.display = 'none';
            return 0;
        }
        var frames = Math.max(1, asset.frames || 1);
        var aspect = asset.framewidth && asset.frameheight
            ? asset.framewidth / asset.frameheight
            : fallbackAspect;
        win.style.display = '';
        win.style.aspectRatio = String(aspect);
        strip.style.backgroundImage = 'url("' + asset.url + '")';
        strip.style.width = (frames * 100) + '%';
        var duration = frames * (asset.frameduration || 80);
        strip.style.animation = frames > 1
            ? 'smg-rpg-play ' + duration + 'ms steps(' + frames + ') ' + (loop ? 'infinite' : '1 forwards')
            : 'none';
        return duration;
    }

    /**
     * Build a still prop from an asset.
     *
     * @param {Object|undefined} asset The asset.
     * @param {string} className Classes for the prop.
     * @param {string} tag The element to create.
     * @returns {Element} The prop.
     */
    function makeProp(asset, className, tag) {
        var el = document.createElement(tag || 'div');
        el.className = 'smg-rpg-prop ' + className;
        if (asset) {
            el.style.backgroundImage = 'url("' + asset.url + '")';
        }
        return el;
    }

    /**
     * Write a navigation control's caption and accessible name.
     *
     * The caption is visible text under the control, the name is what a screen reader says. Both
     * state the action and, while the way is closed, why.
     *
     * @param {Element} el The control.
     * @param {string} label What the control does.
     * @param {boolean} locked Whether the way is closed.
     * @returns {void}
     */
    function setNavCaption(el, label, locked) {
        var caption = el.querySelector('.smg-rpg-navcaption');
        if (!caption) {
            caption = document.createElement('span');
            caption.className = 'smg-rpg-navcaption';
            el.appendChild(caption);
        }
        var lockedText = M.util.get_string('navlocked', 'local_stackmathgame');
        caption.replaceChildren(document.createTextNode(label));
        if (locked) {
            var reason = document.createElement('small');
            reason.textContent = lockedText;
            caption.appendChild(reason);
        }
        el.setAttribute('aria-label', locked ? label + '. ' + lockedText : label);
        el.setAttribute('data-smg-navlabel', label);
    }

    /**
     * Open or close a navigation control.
     *
     * A closed control stays in the tab order with aria-disabled, so keyboard and screen reader
     * users learn that it exists and why it does nothing yet; only an open one is a link.
     *
     * @param {HTMLAnchorElement} el The control.
     * @param {string|null} url Where it leads, or null to close it.
     * @param {string} label What the control does.
     * @returns {void}
     */
    function setNavState(el, url, label) {
        var open = !!url;
        el.classList.toggle('is-locked', !open);
        if (open) {
            el.href = url;
            el.removeAttribute('aria-disabled');
            el.removeAttribute('role');
            el.removeAttribute('tabindex');
        } else {
            el.removeAttribute('href');
            el.setAttribute('aria-disabled', 'true');
            el.setAttribute('role', 'link');
            el.setAttribute('tabindex', '0');
        }
        setNavCaption(el, label, !open);
    }

    /**
     * Create a wooden arrow, closed until the server opens it.
     *
     * @param {Object|undefined} asset The arrow artwork.
     * @param {string} direction "back" or "next".
     * @param {string} label What the control does.
     * @returns {HTMLAnchorElement} The control.
     */
    function makeArrow(asset, direction, label) {
        var a = document.createElement('a');
        a.className = 'smg-rpg-arrow is-' + direction + (direction === 'next' ? ' smg-rpg-next' : '');
        if (asset) {
            a.style.backgroundImage = 'url("' + asset.url + '")';
        }
        setNavState(a, null, label);
        return a;
    }

    /**
     * Open or close the way forward on a control, from the server's navigation decision.
     *
     * The control stays visible either way - a grey arrow says "not yet", which is information;
     * a missing arrow says nothing. Only an open arrow is a link.
     *
     * @param {HTMLAnchorElement} el The control.
     * @param {Object} decision The decision from GameCore.navigationFrom().
     * @returns {void}
     */
    function applyArrow(el, decision) {
        if (!el) {
            return;
        }
        var open = !!(decision && decision.hasNext && decision.url);
        el.classList.toggle('smg-nav-finish', !!(decision && decision.isEnd));
        var label = (decision && decision.label) || el.getAttribute('data-smg-navlabel') || '';
        setNavState(el, open ? decision.url : null, label);
    }

    /**
     * Put the question into the panel, whole.
     *
     * Question text, inputs - with whatever editor a site adds to them -, STACK's validation and
     * feedback, the check control and Moodle's outcome stay together as STACK rendered them
     * (issue #15). Splitting the fields off into the scene tore editors apart, clipped toolbars
     * and long equivalence chains, and left the feedback hidden with the rest of the question.
     * Nothing leaves the quiz form, so the answer is still submitted the ordinary way.
     *
     * @param {Element} que The question element.
     * @param {Object} parts The scene parts.
     * @returns {void}
     */
    function mountQuestion(que, parts) {
        var formulation = que ? que.querySelector('.formulation') : null;
        if (!formulation) {
            // Nothing new to mount. After every answer the engine replaces the whole .que with a
            // freshly rendered one; when both refreshes fail it cannot, and the .que on the page
            // is still the old one whose formulation already sits in the panel. Clearing the
            // panel then threw away the question and the player's last input (re-audit N03), so
            // the panel keeps what it shows until there is something to replace it with.
            return;
        }
        // Whatever an earlier mount moved out belongs to the old question and goes. The check
        // control is the engine's single button and is kept.
        var check = document.querySelector('.smg-action-check');
        parts.question.replaceChildren();
        parts.feedback.replaceChildren();

        if (formulation) {
            // The whole formulation, not a .qtext inside it: STACK writes the question text
            // straight into .formulation and has no .qtext. It also carries the hidden inputs -
            // the sequence check among them - which the engine collects from the panel because
            // the panel is marked as a part of the question.
            parts.question.appendChild(formulation);
        }
        if (check) {
            parts.actions.appendChild(check);
        }
        // Nothing to check on a page without an answer field - a welcome or briefing page hides
        // its input on purpose. A field hidden by an editor still counts: the editor is the input.
        var answerable = !!formulation && Array.from(formulation.querySelectorAll(GameCore.STACK_FIELDS))
            .some(function(field) {
                return !field.closest('.d-none');
            });
        parts.actions.hidden = !answerable;
        // Moodle's outcome: general and specific feedback, the right answer where the review
        // options show it. Moved, not copied, so it is announced once and not hidden with .que.
        var outcome = que.querySelector('.outcome');
        if (outcome) {
            parts.feedback.appendChild(outcome);
        }
        // Moved content is new content as far as Moodle's filters are concerned. Without this the
        // maths stays as raw \\(...\\) source: MathJax typeset the page before the question was
        // moved, and does not look again unless told to.
        FilterEvents.notifyFilterContentUpdated([parts.panel]);
    }

    /**
     * Build the scene for the current slot.
     *
     * @param {Object} gameState The state handed to init().
     * @param {Object} assets Assets by key.
     * @param {string} sceneType The scene type of the current slot.
     * @returns {Object} References to the scene's parts.
     */
    function buildScene(gameState, assets, sceneType) {
        var que = GameCore.currentQuestion();
        var meadow = MEADOW_TYPES.indexOf(sceneType) !== -1;
        var boss = sceneType === 'boss' && assets.golem_idle;

        var panel = document.createElement('div');
        panel.className = 'smg-rpg-panel';
        panel.setAttribute('data-smg-question-part', '');
        var stage = document.createElement('div');
        stage.className = 'smg-rpg-stage' + (meadow ? ' smg-rpg-meadow' : '');
        var bg = assets.bg_forest;
        if (meadow) {
            bg = assets.bg_start;
        } else if (boss) {
            bg = assets.bg_forest_light || assets.bg_forest;
        }
        if (bg) {
            stage.style.backgroundImage = 'url("' + bg.url + '")';
        }

        // The panel's three parts: the question as STACK rendered it, the check control, and a
        // live region for the outcome, which is announced when it changes after a check.
        var question = document.createElement('div');
        question.className = 'smg-rpg-question';
        var actions = document.createElement('div');
        actions.className = 'smg-rpg-actions';
        var feedback = document.createElement('div');
        feedback.className = 'smg-rpg-feedback';
        feedback.setAttribute('aria-live', 'polite');
        panel.appendChild(question);
        panel.appendChild(actions);
        panel.appendChild(feedback);

        var parts = {panel: panel, stage: stage, meadow: meadow, que: que,
            question: question, actions: actions, feedback: feedback};

        parts.player = makeSprite(assets.elf_idle, 'smg-rpg-player', 0.776);
        stage.appendChild(parts.player);

        parts.wisp = document.createElement('div');
        parts.wisp.className = 'smg-rpg-wisp';
        stage.appendChild(parts.wisp);

        parts.bubble = document.createElement('div');
        parts.bubble.className = 'smg-rpg-bubble';
        stage.appendChild(parts.bubble);

        if (meadow) {
            var alert = document.createElement('div');
            alert.className = 'smg-rpg-alert';
            alert.textContent = '!!!';
            stage.appendChild(alert);
            stage.appendChild(makeProp(assets.sign_info, 'smg-rpg-sign-info'));
            // On the meadow the signpost is the way on, as in the original.
            parts.next = makeProp(assets.sign_right, 'smg-rpg-sign-right is-locked smg-rpg-next', 'a');
            setNavState(parts.next, null, M.util.get_string('nextscene', 'local_stackmathgame'));
            stage.appendChild(parts.next);
        } else {
            var enemyKey = boss ? 'golem' : 'troll';
            parts.enemyKey = enemyKey;
            parts.enemy = makeSprite(assets[enemyKey + '_idle'], 'smg-rpg-enemy is-' + enemyKey, boss ? 0.933 : 1.293);
            var tag = document.createElement('div');
            tag.className = 'smg-rpg-tag';
            tag.textContent = '?';
            parts.enemy.appendChild(tag);
            stage.appendChild(parts.enemy);

            parts.hud = document.createElement('div');
            parts.hud.className = 'smg-rpg-hud';
            parts.fairies = document.createElement('div');
            parts.fairies.className = 'smg-rpg-counter is-fairies';
            parts.fairies.setAttribute('data-smg-hud', 'fairies');
            parts.fairies.setAttribute('title', M.util.get_string('fairies', 'local_stackmathgame'));
            parts.mana = document.createElement('div');
            parts.mana.className = 'smg-rpg-counter is-mana';
            parts.mana.setAttribute('data-smg-hud', 'mana');
            parts.mana.setAttribute('title', M.util.get_string('mana', 'local_stackmathgame'));
            parts.hud.appendChild(parts.fairies);
            parts.hud.appendChild(parts.mana);
            stage.appendChild(parts.hud);

            parts.back = makeArrow(assets.arrow_nav, 'back', M.util.get_string('previousscene', 'local_stackmathgame'));
            parts.next = makeArrow(assets.arrow_nav, 'next', M.util.get_string('nextscene', 'local_stackmathgame'));
            stage.appendChild(parts.back);
            stage.appendChild(parts.next);
        }

        // Beside the question, not inside it. After every answer the engine swaps the whole .que
        // for a freshly rendered one, and a scene living inside it went with it - the first check
        // left the plain quiz behind. As a sibling of .que it stays inside the quiz form, so the
        // fields it holds are still submitted.
        if (que && que.parentNode) {
            que.parentNode.insertBefore(panel, que);
            que.parentNode.insertBefore(stage, que);
        } else {
            document.body.appendChild(panel);
            document.body.appendChild(stage);
        }
        mountQuestion(que, parts);
        document.body.classList.add('smg-rpg-active');
        return parts;
    }

    /**
     * Size the scene so all of it is on screen, measured rather than assumed.
     *
     * The original scaled its scene by the window width alone, so on a wide screen it grew taller
     * than the viewport and the bottom row - counters and arrows - fell off. A fixed reserve for
     * the space above the scene is no better: the parchment panel is as tall as its question.
     * Measured instead: whatever is left below the scene's top edge sets its height, and the
     * width follows from the aspect ratio, capped at a comfortable maximum.
     *
     * @param {Object} parts The scene parts.
     * @returns {void}
     */
    function fitStage(parts) {
        var apply = function() {
            var column = parts.stage.parentNode ? parts.stage.parentNode.getBoundingClientRect().width : 1200;
            // The panel takes the column, capped - not the stage's width. Tying it to the stage
            // fed back on itself when a maths editor's toolbar made the panel tall: a taller panel
            // left less height for the stage, a narrower stage made the panel narrower, the
            // toolbar wrapped into more rows, and the whole thing collapsed into a thin column.
            var panelWidth = Math.round(Math.min(1200, column));
            parts.panel.style.maxWidth = panelWidth + 'px';

            var top = parts.stage.getBoundingClientRect().top + window.scrollY;
            var available = Math.max(240, window.innerHeight - top - 12);
            var width = Math.min(1200, column, available * 1.5);
            // Never smaller than a usable scene: below this the page scrolls instead of the scene
            // shrinking to nothing under a tall panel.
            width = Math.max(width, Math.min(column, 560));
            parts.stage.style.maxWidth = Math.round(width) + 'px';
        };
        apply();
        window.addEventListener('resize', apply);
        // The panel's height changes when STACK typesets the maths or shows its validation.
        if (window.ResizeObserver) {
            new window.ResizeObserver(apply).observe(parts.panel);
        }
    }

    /**
     * Point the back arrow at the previous page of the attempt.
     *
     * @param {Object} parts The scene parts.
     * @returns {void}
     */
    function wireBackArrow(parts) {
        if (!parts.back) {
            return;
        }
        var url = new URL(window.location.href);
        var page = parseInt(url.searchParams.get('page') || '0', 10) || 0;
        if (page <= 0) {
            return;
        }
        url.searchParams.set('page', String(page - 1));
        setNavState(parts.back, url.toString(), M.util.get_string('previousscene', 'local_stackmathgame'));
    }

    /**
     * Show the counters.
     *
     * @param {Object} parts The scene parts.
     * @param {Object} score The counters.
     * @returns {void}
     */
    function updateHUD(parts, score) {
        if (parts.fairies) {
            parts.fairies.textContent = String(score.fairies);
        }
        if (parts.mana) {
            parts.mana.textContent = String(score.mana);
        }
    }

    /**
     * Play the victory: the monster falls and fades, the elf walks to the middle.
     *
     * @param {Object} parts The scene parts.
     * @param {Object} assets Assets by key.
     * @param {boolean} animate False to jump straight to the end state (a scene already solved).
     * @returns {void}
     */
    function defeatEnemy(parts, assets, animate) {
        if (!parts.enemy) {
            return;
        }
        var tag = parts.enemy.querySelector('.smg-rpg-tag');
        if (tag) {
            tag.remove();
        }
        if (!animate) {
            parts.enemy.classList.add('is-gone');
            parts.player.classList.add('is-center');
            return;
        }
        var dieAsset = assets[parts.enemyKey + '_die'];
        var dieTime = dieAsset ? setSprite(parts.enemy, dieAsset, 1.293, false) : 0;
        window.setTimeout(function() {
            parts.enemy.classList.add('is-gone');
        }, Math.max(400, dieTime));

        if (assets.elf_run) {
            setSprite(parts.player, assets.elf_run, 0.776, true);
        }
        parts.player.classList.add('is-center');
        window.setTimeout(function() {
            setSprite(parts.player, assets.elf_idle, 0.776, true);
        }, 1250);
    }

    /**
     * Say so when artwork fails to load.
     *
     * The bundled default design is a set of labelled placeholders on purpose; a package whose
     * files are missing looks broken in the same way. A failed image is reported in the console
     * with its key and once on the stage, so the two can be told apart.
     *
     * @param {Object} assets Assets by key.
     * @param {Element} stage The stage.
     * @returns {void}
     */
    function reportMissingAssets(assets, stage) {
        var shown = false;
        Object.keys(assets).forEach(function(key) {
            var asset = assets[key];
            if (!asset || !asset.url) {
                return;
            }
            var probe = new Image();
            probe.onerror = function() {
                window.console.warn('[stackmathgame] Design asset could not be loaded: ' + key + ' (' + asset.url + ')');
                if (shown) {
                    return;
                }
                shown = true;
                var note = document.createElement('div');
                note.className = 'smg-rpg-assetwarning';
                note.setAttribute('role', 'status');
                note.textContent = M.util.get_string('assetmissing', 'local_stackmathgame');
                stage.appendChild(note);
            };
            probe.src = asset.url;
        });
    }

    /**
     * Start the mode on the current page.
     *
     * @param {Object} gameState The state handed over by the engine.
     * @returns {{onAnswer: function}} The mode's contract with the engine.
     */
    function init(gameState) {
        injectStyles();

        var slotMap = buildSlotMap(gameState.questionmap);
        var assets = indexAssets(gameState);
        var currentSlot = parseInt(gameState.currentslot || 0, 10) || 0;
        var cfg = slotMap[String(currentSlot)] || GameCore.defaultConfig();
        var sceneType = cfg.scene && cfg.scene.type ? cfg.scene.type : 'challenge';

        var parts = buildScene(gameState, assets, sceneType);
        wireBackArrow(parts);
        fitStage(parts);
        reportMissingAssets(assets, parts.stage);

        var config = gameState.config || {};
        forgetOtherPlayers(config.userid);
        var cacheKey = cacheKeyFor(config);
        var score = chooseScore(gameState.profile ? computeScore(gameState, slotMap) : null, readCachedScore(cacheKey));
        writeCachedScore(cacheKey, score);
        updateHUD(parts, score);

        var intro = cfg.narrative && cfg.narrative.intro ? cfg.narrative.intro : '';
        parts.bubble.textContent = intro;

        // The way on is open at once where nothing has to be answered, and where the scene was
        // already solved on an earlier visit. Everywhere else it opens with the answer.
        var solvedBefore = currentSlot > 0 && GameCore.isSlotSolved(gameState, currentSlot);
        if (sceneType === 'instruction' || solvedBefore) {
            applyArrow(parts.next, GameCore.navigationFrom({navigation: gameState.navigation}));
        }
        if (solvedBefore) {
            defeatEnemy(parts, assets, false);
        }

        return {
            /**
             * React to a graded answer.
             *
             * @param {Object} response The submit_answer response.
             * @returns {void}
             */
            onAnswer: function(response) {
                mountQuestion(GameCore.currentQuestion(), parts);
                var slot = response.slot || currentSlot;
                var slotCfg = slotMap[String(slot)] || GameCore.defaultConfig();
                var type = slotCfg.scene && slotCfg.scene.type ? slotCfg.scene.type : 'challenge';

                // Deliberately firstsolve, not cannext: cannext stays true for every later
                // correct answer to a solved scene, and awarding on it let mana be farmed.
                if (response.profile) {
                    // The profile that came back with the answer is the server's record after
                    // it: the counters are rebuilt from it rather than incremented on top of
                    // whatever the page believed before.
                    score = computeScore({profile: response.profile}, slotMap);
                } else if (response.firstsolve) {
                    var gain = MANA_GAIN[type] !== undefined ? MANA_GAIN[type] : 10;
                    score.mana = Math.min(MANA_MAX, score.mana + gain);
                    score.fairies++;
                }
                if (response.firstsolve) {
                    defeatEnemy(parts, assets, true);
                }
                writeCachedScore(cacheKey, score);
                updateHUD(parts, score);

                var key = response.cannext ? 'success' : 'fail';
                parts.bubble.textContent = slotCfg.narrative && slotCfg.narrative[key]
                    ? slotCfg.narrative[key]
                    : '';

                var decision = GameCore.navigationFrom(response);
                applyArrow(parts.next, decision);
                GameCore.applyGroupProgress(
                    stageCounter(parts),
                    decision,
                    M.util.get_string('stageprogress', 'local_stackmathgame')
                );
            }
        };
    }

    /**
     * Return the stage counter element, created on first use.
     *
     * Created lazily: a quiz whose pages hold one question each never has a group.
     *
     * @param {Object} parts The scene parts.
     * @returns {Element} The element.
     */
    function stageCounter(parts) {
        var existing = parts.stage.querySelector('.smg-rpg-stagecount');
        if (existing) {
            return existing;
        }
        var el = document.createElement('div');
        el.className = 'smg-rpg-stagecount d-none';
        el.setAttribute('aria-live', 'polite');
        parts.stage.appendChild(el);
        return el;
    }

    return {
        init: init,
        // Exposed for the unit tests of the counter cache (re-audit N07); not part of the mode
        // contract, which is init() alone.
        cacheKeyFor: cacheKeyFor,
        chooseScore: chooseScore,
        forgetOtherPlayers: forgetOtherPlayers
    };
});
