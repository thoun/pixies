import type { BgaZoom as BgaZoomType } from "../../bga-zoom";

const BgaZoom: typeof BgaZoomType = await globalThis.importEsmLib('bga-zoom', '1.x');

const [BgaHelp, BgaAnimations, BgaCards] = await globalThis.importDojoLibs([
    g_gamethemeurl + "modules/js/bga-help.js",
    g_gamethemeurl + "modules/js/bga-animations.js",
    g_gamethemeurl + "modules/js/bga-cards.js",
]);

export { BgaZoom, BgaHelp, BgaAnimations, BgaCards };