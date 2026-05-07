import type DataTables from "datatables.net";
import type Dropzone from "dropzone";
import type jQuery from "jquery";
import type _ from "lodash";
import type noUiSlider from "nouislider";
import type { Calendar } from "vanilla-calendar-pro";

type JQueryStatic = typeof jQuery;

declare global {
  interface Window {
    $: JQueryStatic;
    jQuery: JQueryStatic;
    _: typeof _;
    Dropzone: typeof Dropzone;
    noUiSlider: typeof noUiSlider;
    DataTable: typeof DataTables;
    VanillaCalendarPro: typeof Calendar;
    HSStaticMethods?: {
      autoInit: (collection?: string[]) => void;
    };
  }
}

export {};
