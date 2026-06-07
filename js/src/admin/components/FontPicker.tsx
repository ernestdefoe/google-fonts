import app from 'flarum/admin/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import type Mithril from 'mithril';
import { GOOGLE_FONTS } from '../../common/fonts';

const DATALIST_ID = 'ernestdefoe-gf-list';
const KEY = 'ernestdefoe-google-fonts.';

/** Weights offered for self-hosted uploads, heaviest concept last. */
const WEIGHTS: { value: number; label: string }[] = [
  { value: 100, label: 'Thin' },
  { value: 200, label: 'Extra Light' },
  { value: 300, label: 'Light' },
  { value: 400, label: 'Regular' },
  { value: 500, label: 'Medium' },
  { value: 600, label: 'Semi Bold' },
  { value: 700, label: 'Bold' },
  { value: 800, label: 'Extra Bold' },
  { value: 900, label: 'Black' },
];

interface Face {
  weight: number;
  url: string;
}

interface FontPickerAttrs {
  /** Which slot this picker controls; selects the API slot + settings keys. */
  slot: 'body' | 'heading';
  /** bidi stream from AdminPage.setting(key) — the (deferred-save) family name. */
  stream: (value?: string) => string;
  label: Mithril.Children;
  help?: Mithril.Children;
  heading?: boolean;
}

const api = (path: string) => app.forum.attribute('apiUrl') + path;
const t = (k: string, params?: Record<string, unknown>) =>
  app.translator.trans('ernestdefoe-google-fonts.admin.' + k, params);

/**
 * A font picker with two modes:
 *
 *   - "google"  — a datalist-backed text input over the Google Fonts library
 *     (also accepts any typed family) plus a live preview. Saved via the
 *     deferred Save button.
 *
 *   - "upload"  — self-host the font: upload one .woff2 per weight. Files and
 *     the family name are persisted immediately (server-side), independent of
 *     the Save button. This path works in regions where Google is blocked.
 */
export default class FontPicker extends Component<FontPickerAttrs> {
  private mode: 'google' | 'upload' = 'google';
  private faces: Face[] = [];
  private pendingWeight = 400;
  private uploading = false;
  private error: string | null = null;

  oninit(vnode: Mithril.Vnode<FontPickerAttrs, this>) {
    super.oninit(vnode);
    this.faces = this.readFaces();
    this.mode = this.faces.length ? 'upload' : 'google';
    this.pendingWeight = this.firstUnusedWeight();
    FontPicker.ensureDatalist();
    if (this.mode === 'google') FontPicker.ensureGooglePreview(this.attrs.stream());
    else this.refreshUploadPreview();
  }

  view(): Mithril.Children {
    return (
      <div className="Form-group GoogleFontPicker">
        <label>{this.attrs.label}</label>
        {this.attrs.help && <div className="helpText">{this.attrs.help}</div>}
        {this.mode === 'google' ? this.googleMode() : this.uploadMode()}
        {this.error && <div className="GoogleFontPicker-error">{this.error}</div>}
      </div>
    );
  }

  // ---- Google mode ------------------------------------------------------

  private googleMode(): Mithril.Children {
    const value = (this.attrs.stream() || '').trim();
    const stack = value ? `"${value}", system-ui, sans-serif` : 'inherit';

    return [
      <div className="GoogleFontPicker-row">
        <input
          className="FormControl GoogleFontPicker-input"
          list={DATALIST_ID}
          spellcheck={false}
          placeholder={t('placeholder')}
          value={value}
          oninput={(e: Event) => {
            const v = (e.target as HTMLInputElement).value;
            this.attrs.stream(v);
            FontPicker.ensureGooglePreview(v);
          }}
        />
        {value && (
          <button
            type="button"
            className="Button GoogleFontPicker-clear"
            title={t('clear') as string}
            onclick={() => this.attrs.stream('')}
          >
            <i className="fas fa-times" />
          </button>
        )}
      </div>,

      <div
        className={'GoogleFontPicker-preview' + (this.attrs.heading ? ' GoogleFontPicker-preview--heading' : '')}
        style={{ fontFamily: stack }}
      >
        {value ? t('preview_text') : t('no_font')}
      </div>,

      <button type="button" className="Button Button--text GoogleFontPicker-switch" onclick={() => this.switchToUpload()}>
        <i className="fas fa-cloud-arrow-up" /> {t('switch_to_upload')}
      </button>,
    ];
  }

  // ---- Upload (self-hosted) mode ---------------------------------------

  private uploadMode(): Mithril.Children {
    const family = (this.attrs.stream() || '').trim();
    const stack = family ? `"${family}", system-ui, sans-serif` : 'inherit';
    const used = new Set(this.faces.map((f) => f.weight));
    const available = WEIGHTS.filter((w) => !used.has(w.value));

    return [
      <div className="GoogleFontPicker-hint">{t('upload_hint')}</div>,

      <label className="GoogleFontPicker-familyLabel">{t('family_name')}</label>
      ,
      <input
        className="FormControl"
        spellcheck={false}
        placeholder={t('family_placeholder')}
        value={family}
        oninput={(e: Event) => this.attrs.stream((e.target as HTMLInputElement).value)}
        onchange={() => this.saveFamily()}
      />,

      this.faces.length > 0 && (
        <ul className="GoogleFontPicker-faces">
          {this.faces.map((f) => {
            const label = WEIGHTS.find((w) => w.value === f.weight);
            return (
              <li className="GoogleFontPicker-face" key={f.weight}>
                <span className="GoogleFontPicker-faceWeight">
                  {f.weight} <span className="GoogleFontPicker-faceName">{label ? label.label : ''}</span>
                </span>
                <button
                  type="button"
                  className="Button Button--icon GoogleFontPicker-faceRemove"
                  title={t('remove_weight') as string}
                  onclick={() => this.removeFace(f.weight)}
                >
                  <i className="fas fa-times" />
                </button>
              </li>
            );
          })}
        </ul>
      ),

      <div className="GoogleFontPicker-addRow">
        <select
          className="FormControl GoogleFontPicker-weightSelect"
          value={String(this.pendingWeight)}
          disabled={this.uploading || available.length === 0}
          onchange={(e: Event) => {
            this.pendingWeight = parseInt((e.target as HTMLSelectElement).value, 10);
          }}
        >
          {available.map((w) => (
            <option value={String(w.value)}>
              {w.value} — {w.label}
            </option>
          ))}
        </select>

        <label className={'Button GoogleFontPicker-addBtn' + (this.uploading || available.length === 0 ? ' disabled' : '')}>
          {this.uploading ? (
            <span>
              <LoadingIndicator display="inline" size="small" /> {t('uploading')}
            </span>
          ) : (
            <span>
              <i className="fas fa-cloud-arrow-up" /> {t('add_weight')}
            </span>
          )}
          <input
            type="file"
            accept=".woff2"
            disabled={this.uploading || available.length === 0}
            onchange={(e: any) => {
              const file = e.target.files?.[0];
              if (file) this.upload(file);
              e.target.value = '';
            }}
          />
        </label>
      </div>,

      this.faces.length > 0 && (
        <div
          className={'GoogleFontPicker-preview' + (this.attrs.heading ? ' GoogleFontPicker-preview--heading' : '')}
          style={{ fontFamily: stack }}
        >
          {family ? t('preview_text') : t('name_your_font')}
        </div>
      ),

      <button type="button" className="Button Button--text GoogleFontPicker-switch" onclick={() => this.switchToGoogle()}>
        <i className="fab fa-google" /> {t('switch_to_google')}
      </button>,
    ];
  }

  // ---- Mode switching ---------------------------------------------------

  private switchToUpload(): void {
    this.mode = 'upload';
    this.error = null;
    this.pendingWeight = this.firstUnusedWeight();
    this.refreshUploadPreview();
  }

  private switchToGoogle(): void {
    // Drop every uploaded weight server-side, then clear the (custom) family
    // so the Google selector starts blank.
    this.error = null;
    this.deleteFaces()
      .then(() => {
        this.faces = [];
        this.writeFacesToSettings();
        this.attrs.stream('');
        this.saveFamily();
        this.mode = 'google';
        FontPicker.ensureGooglePreview('');
        m.redraw();
      })
      .catch((e: any) => this.fail(e));
  }

  // ---- Server interactions ---------------------------------------------

  private upload(file: File): void {
    if (this.uploading) return;
    if (!/\.woff2$/i.test(file.name)) {
      this.error = t('only_woff2') as string;
      m.redraw();
      return;
    }

    this.uploading = true;
    this.error = null;
    m.redraw();

    const body = new FormData();
    body.append('slot', this.attrs.slot);
    body.append('weight', String(this.pendingWeight));
    body.append('font', file);

    app
      .request<any>({
        method: 'POST',
        url: api('/ernestdefoe/google-fonts/font'),
        serialize: (raw: any) => raw,
        body,
      })
      .then((res: any) => {
        const attrs = res?.data?.attributes || {};
        this.faces = Array.isArray(attrs.faces) ? attrs.faces : this.faces;
        this.writeFacesToSettings();

        // Adopt the server-derived family if we don't have one yet, and keep
        // the deferred-save baseline in sync so it isn't flagged dirty.
        if (attrs.family && !(this.attrs.stream() || '').trim()) {
          this.attrs.stream(attrs.family);
          app.data.settings[KEY + this.attrs.slot + '_font'] = attrs.family;
        }

        this.uploading = false;
        this.pendingWeight = this.firstUnusedWeight();
        this.refreshUploadPreview();
        m.redraw();
      })
      .catch((e: any) => {
        this.uploading = false;
        this.fail(e);
      });
  }

  private removeFace(weight: number): void {
    app
      .request<any>({
        method: 'DELETE',
        url: api('/ernestdefoe/google-fonts/font'),
        body: { slot: this.attrs.slot, weight },
      })
      .then((res: any) => {
        const attrs = res?.data?.attributes || {};
        this.faces = Array.isArray(attrs.faces) ? attrs.faces : [];
        this.writeFacesToSettings();
        this.pendingWeight = this.firstUnusedWeight();
        this.refreshUploadPreview();
        m.redraw();
      })
      .catch((e: any) => this.fail(e));
  }

  private deleteFaces(): Promise<any> {
    return app.request<any>({
      method: 'DELETE',
      url: api('/ernestdefoe/google-fonts/font'),
      body: { slot: this.attrs.slot },
    });
  }

  private saveFamily(): void {
    const family = (this.attrs.stream() || '').trim();
    app
      .request<any>({
        method: 'POST',
        url: api('/ernestdefoe/google-fonts/font-family'),
        body: { slot: this.attrs.slot, family },
      })
      .then((res: any) => {
        const saved = res?.data?.attributes?.family ?? family;
        // Sync the deferred-save baseline so the Save button stays clean and
        // reflect the server's sanitised value.
        this.attrs.stream(saved);
        app.data.settings[KEY + this.attrs.slot + '_font'] = saved;
        this.refreshUploadPreview();
        m.redraw();
      })
      .catch((e: any) => this.fail(e));
  }

  // ---- State helpers ----------------------------------------------------

  private readFaces(): Face[] {
    try {
      const raw = app.data.settings[KEY + this.attrs.slot + '_font_faces'];
      const arr = raw ? JSON.parse(raw) : [];
      return Array.isArray(arr)
        ? arr
            .filter((f: any) => f && typeof f.weight === 'number' && typeof f.url === 'string')
            .map((f: any) => ({ weight: f.weight, url: f.url }))
        : [];
    } catch {
      return [];
    }
  }

  private writeFacesToSettings(): void {
    const key = KEY + this.attrs.slot + '_font_faces';
    if (this.faces.length) app.data.settings[key] = JSON.stringify(this.faces);
    else delete app.data.settings[key];
  }

  private firstUnusedWeight(): number {
    const used = new Set(this.faces.map((f) => f.weight));
    const free = WEIGHTS.find((w) => !used.has(w.value));
    return free ? free.value : 400;
  }

  private fail(e: any): void {
    this.error = e?.response?.errors?.[0]?.detail || (t('upload_error') as string);
    m.redraw();
  }

  // ---- Previews ---------------------------------------------------------

  /** Inject @font-face for the uploaded weights so the preview renders. */
  private refreshUploadPreview(): void {
    if (typeof document === 'undefined') return;
    const family = (this.attrs.stream() || '').replace(/[^A-Za-z0-9 ]/g, '').trim();
    const id = 'ernestdefoe-gf-upload-prev-' + this.attrs.slot;
    let style = document.getElementById(id) as HTMLStyleElement | null;
    if (!family || !this.faces.length) {
      if (style) style.remove();
      return;
    }
    if (!style) {
      style = document.createElement('style');
      style.id = id;
      document.head.appendChild(style);
    }
    style.textContent = this.faces
      .map(
        (f) =>
          `@font-face{font-family:"${family}";font-style:normal;font-weight:${f.weight};` +
          `font-display:swap;src:url("${f.url}") format("woff2");}`
      )
      .join('');
  }

  /** Build the shared <datalist> of all fonts once (outside the Mithril tree). */
  private static ensureDatalist(): void {
    if (typeof document === 'undefined' || document.getElementById(DATALIST_ID)) return;
    const dl = document.createElement('datalist');
    dl.id = DATALIST_ID;
    for (const fam of GOOGLE_FONTS) {
      const opt = document.createElement('option');
      opt.value = fam;
      dl.appendChild(opt);
    }
    document.body.appendChild(dl);
  }

  /** Load a Google <link> for the given family so the admin preview renders. */
  private static ensureGooglePreview(family: string): void {
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
