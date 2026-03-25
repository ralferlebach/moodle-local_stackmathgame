// local_stackmathgame/sprite_engine.js
//
// Sprite-sheet animation engine. Completely isolated from game logic.
// Works with CSS transform translateX for performance (no canvas needed).
//
// Supports:
//  - Infinite looping
//  - Play-once with callback
//  - Play-to-end (freeze on last frame)
//  - Responsive scaling via CSS custom property --sprite-scale
//
// @package    local_stackmathgame

define([], function () {

    'use strict';

    // =========================================================================
    // SpriteSheet descriptor
    // =========================================================================

    class SpriteSheet {
        /**
         * @param {Object} cfg
         * @param {string} cfg.file        Filename relative to theme's sprites/ dir
         * @param {string} cfg.url         Fully resolved URL (set by GameElement)
         * @param {number} cfg.w           Frame width in px
         * @param {number} cfg.h           Frame height in px
         * @param {number} cfg.frames      Number of frames
         * @param {number} cfg.interval    MS per frame
         */
        constructor(cfg) {
            this.url      = cfg.url      || cfg.file;
            this.w        = cfg.w;
            this.h        = cfg.h;
            this.frames   = cfg.frames;
            this.interval = cfg.interval;

            // Animation state (per-instance, reset on each setState call).
            this._curFrame  = 0;
            this._timerId   = null;
        }
    }

    // =========================================================================
    // GameElement — base DOM-backed animated sprite
    // =========================================================================

    class GameElement {
        /**
         * @param {Object.<string, SpriteSheet>} sprites  State → SpriteSheet map
         * @param {boolean} flipped  Mirror sprite horizontally
         */
        constructor(sprites, flipped = false) {
            this.sprites  = sprites;
            this.flipped  = flipped;
            this.state    = 'idle';

            this.container = null;
            this.imgNode   = null;

            this._buildDOM();
            this._applyScale();
            this.startAnimation('infinite');
        }

        // ------------------------------------------------------------------
        // DOM
        // ------------------------------------------------------------------

        _buildDOM() {
            const sp   = this.sprites['idle'];
            const wrap = document.createElement('div');
            wrap.style.cssText = [
                `width:${sp.w}px`,
                `height:${sp.h}px`,
                'overflow:hidden',
                'position:absolute',
            ].join(';');

            const img = document.createElement('img');
            img.src         = sp.url;
            img.style.cssText = 'width:auto;height:100%;';
            if (this.flipped) { img.style.transform = 'scaleX(-1)'; }

            // Preload all sprite sheets.
            Object.values(this.sprites).forEach(sheet => {
                const pre = new Image();
                pre.src = sheet.url;
            });

            wrap.appendChild(img);
            this.container = wrap;
            this.imgNode   = img;
        }

        // Apply responsive scale via CSS custom property (set by breakpoint CSS).
        _applyScale() {
            const scale = parseFloat(
                getComputedStyle(document.documentElement)
                    .getPropertyValue('--stackmathgame-sprite-scale')
            ) || 0.33;

            const sp = this.sprites[this.state];
            this.container.style.width  = Math.round(sp.w * scale) + 'px';
            this.container.style.height = Math.round(sp.h * scale) + 'px';
        }

        // ------------------------------------------------------------------
        // Animation control
        // ------------------------------------------------------------------

        /**
         * @param {'infinite'|'once'|'toend'} repeat
         * @param {string}   backto     State to return to after 'once'
         * @param {Function} callback   Called when 'toend' finishes
         */
        startAnimation(repeat = 'infinite', backto = 'idle', callback = null) {
            const sp = this.sprites[this.state];
            if (!sp) return;

            sp._curFrame = 0;
            sp._timerId  = setInterval(
                () => this._tick(sp, repeat, backto, callback),
                sp.interval
            );
        }

        stopAnimation() {
            const sp = this.sprites[this.state];
            if (sp && sp._timerId) {
                clearInterval(sp._timerId);
                sp._timerId = null;
            }
        }

        setState(newState, repeat = 'infinite', backto = 'idle', callback = null) {
            if (!this.sprites[newState]) {
                console.warn('[SpriteEngine] Unknown state:', newState);
                return;
            }
            this.stopAnimation();
            this.state = newState;
            this.imgNode.src = this.sprites[newState].url;
            this._applyScale();
            this.startAnimation(repeat, backto, callback);
        }

        _tick(sp, repeat, backto, callback) {
            sp._curFrame = (sp._curFrame + 1) % sp.frames;

            const isLastFrame = sp._curFrame === sp.frames - 1;

            // Advance to translateX position.
            const frame    = this.flipped ? (sp.frames - sp._curFrame - 1) : sp._curFrame;
            const offset   = this.flipped ? '' : '-';
            const pct      = (frame / sp.frames) * 100;
            const transform = (this.flipped ? 'scaleX(-1) ' : '') + `translateX(${offset}${pct}%)`;
            this.imgNode.style.transform = transform;

            if (sp._curFrame === 0) {
                // Completed one loop.
                if (repeat === 'once') {
                    this.stopAnimation();
                    this.setState(backto);
                } else if (repeat === 'toend') {
                    this.stopAnimation();
                    sp._curFrame = sp.frames - 1;
                    if (callback) callback();
                }
            }
        }

        /** Resize to a target pixel width, keeping aspect ratio. */
        setWidth(targetWidth) {
            const sp = this.sprites[this.state];
            const h  = Math.round(sp.h * (targetWidth / sp.w));
            this.container.style.width  = targetWidth + 'px';
            this.container.style.height = h + 'px';
        }
    }

    // =========================================================================
    // GameElementWithFormula — enemy variant that carries a formula overlay
    // =========================================================================

    class GameElementWithFormula extends GameElement {
        constructor(sprites, flipped = false) {
            super(sprites, flipped);

            // Formula overlay.
            this.formulaContainer = document.createElement('div');
            this.formulaContainer.classList.add('smg-formula-container');
            this.container.style.overflow = 'visible';
            this.container.appendChild(this.formulaContainer);

            // Fairy placeholder (hover anchor).
            this.fairyPlaceHolder = document.createElement('div');
            this.fairyPlaceHolder.classList.add('smg-fairy-placeholder-at-enemy');
            this.container.appendChild(this.fairyPlaceHolder);

            // Input replacer span (shows "?" where the answer input is).
            this.inputReplacer = null;
        }

        clearFormulas() {
            while (this.formulaContainer.firstChild) {
                this.formulaContainer.removeChild(this.formulaContainer.firstChild);
            }
        }

        setInputReplacer(html) {
            if (!this.inputReplacer) {
                this.inputReplacer = document.createElement('span');
                this.inputReplacer.classList.add('smg-input-replacer');
                this.formulaContainer.appendChild(this.inputReplacer);
            }
            this.inputReplacer.textContent = '?';
        }
    }

    // =========================================================================
    // Factory: build a GameElement from a theme config entry
    // =========================================================================

    /**
     * Build a GameElement (or GameElementWithFormula for enemies) from theme JSON.
     *
     * @param {Object}  themeEntry   One entry from theme's player/enemies array
     * @param {string}  assetBaseUrl Base URL for sprites (ends with /)
     * @param {boolean} isEnemy      Whether to use GameElementWithFormula
     * @param {boolean} flipped
     * @returns {GameElement|GameElementWithFormula}
     */
    function buildFromTheme(themeEntry, assetBaseUrl, isEnemy = false, flipped = false) {
        const spriteDefs = themeEntry.sprites;
        const sprites    = {};

        Object.entries(spriteDefs).forEach(([state, def]) => {
            sprites[state] = new SpriteSheet({
                url:      assetBaseUrl + 'sprites/' + def.file,
                w:        def.w,
                h:        def.h,
                frames:   def.frames,
                interval: def.interval,
            });
        });

        return isEnemy
            ? new GameElementWithFormula(sprites, flipped)
            : new GameElement(sprites, flipped);
    }

    // =========================================================================
    // Public API
    // =========================================================================

    return {
        SpriteSheet,
        GameElement,
        GameElementWithFormula,
        buildFromTheme,
    };
});
