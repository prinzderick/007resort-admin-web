/*
 * Form-control library: registers the Alpine components used by resources/views/components/form/*.
 * Livewire ships Alpine, so we only register data on `alpine:init`. Pure logic lives in ./lib.js (unit-tested).
 */
import { register as numeric } from './numeric.js';
import { register as time } from './time.js';
import { register as select } from './select.js';
import { register as misc } from './misc.js';
import * as lib from './lib.js';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;
    numeric(Alpine);
    time(Alpine);
    select(Alpine);
    misc(Alpine);
});

window.R007Forms = Object.assign(window.R007Forms || { loaders: {} }, { lib });
