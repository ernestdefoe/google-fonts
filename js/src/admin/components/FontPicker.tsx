import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import type Mithril from 'mithril';
import { GOOGLE_FONTS } from '../../common/fonts';

const DATALIST_ID = 'ernestdefoe-gf-list';

interface FontPickerAttrs {
  /** bidi stream from AdminPage.setting(key) — read with stream(), set with stream(v). */
  stream: (value?: string) => string;
  label: Mithril.Children;
  help?: Mithril.Children;
  heading?: boolean;
}

/**
 * A Google Font picker: a native datalist-backed text input (searchable
 * autocomplete over the full font list, while still accepting any typed family
 * name) plus a live preview rendered in the actual font.
 */
export default class FontPicker extends Component<FontPickerAttrs> {
  oninit(vnode: Mithril.Vnode<FontPickerAttrs, this>) {
    super.oninit(vnode);
    FontPicker.ensureDatalist();
    FontPicker.ensurePreviewFont(this.attrs.stream());
  }

  view(): Mithril.Children {
    const value = (this.attrs.stream() || '').trim();
    const stack = value ? `"${value}", system-ui, sans-serif` : 'inherit';

    return (
      <div className="Form-group GoogleFontPicker">
        <label>{this.attrs.label}</label>
        {this.attrs.help && <div className="helpText">{this.attrs.help}</div>}

        <div className="GoogleFontPicker-row">
          <input
            className="FormControl GoogleFontPicker-input"
            list={DATALIST_ID}
            spellcheck={false}
            placeholder={app.translator.trans('ernestdefoe-google-fonts.admin.placeholder')}
            value={value}
            oninput={(e: Event) => {
              const v = (e.target as HTMLInputElement).value;
              this.attrs.stream(v);
              FontPicker.ensurePreviewFont(v);
            }}
          />
          {value && (
            <button
              type="button"
              className="Button GoogleFontPicker-clear"
              title={app.translator.trans('ernestdefoe-google-fonts.admin.clear') as string}
              onclick={() => this.attrs.stream('')}
            >
              <i className="fas fa-times" />
            </button>
          )}
        </div>

        <div
          className={'GoogleFontPicker-preview' + (this.attrs.heading ? ' GoogleFontPicker-preview--heading' : '')}
          style={{ fontFamily: stack }}
        >
          {value
            ? app.translator.trans('ernestdefoe-google-fonts.admin.preview_text')
            : app.translator.trans('ernestdefoe-google-fonts.admin.no_font')}
        </div>
      </div>
    );
  }

  /** Build the shared <datalist> of all fonts once (outside the Mithril tree). */
  private static ensureDatalist(): void {
    if (typeof document === 'undefined' || document.getElementById(DATALIST_ID)) return;
    const dl = document.createElement('datalist');
    dl.id = DATALIST_ID;
    for (const family of GOOGLE_FONTS) {
      const opt = document.createElement('option');
      opt.value = family;
      dl.appendChild(opt);
    }
    document.body.appendChild(dl);
  }

  /** Load a <link> for the given family so the admin preview renders correctly. */
  private static ensurePreviewFont(family: string): void {
    if (typeof document === 'undefined') return;
    family = (family || '').replace(/[^A-Za-z0-9 ]/g, '').trim();
    if (!family) return;
    const id = 'ernestdefoe-gf-prev-' + family.replace(/[^A-Za-z0-9]/g, '-');
    if (document.getElementById(id)) return;
    const link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = 'https://fonts.googleapis.com/css2?family=' + family.replace(/ /g, '+') + ':wght@400;700&display=swap';
    document.head.appendChild(link);
  }
}
