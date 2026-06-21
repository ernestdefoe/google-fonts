import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import FontPicker from './components/FontPicker';

const KEY = 'ernestdefoe-google-fonts.';

const t = (k: string, params?: Record<string, unknown>) =>
  app.translator.trans('ernestdefoe-google-fonts.admin.' + k, params);

// A link to the Google Fonts library, woven into the body-font help text so
// admins can jump straight there to browse families (#1).
const fontsLibraryLink = () => (
  <a href="https://fonts.google.com/" target="_blank" rel="noopener noreferrer" />
);

export const extend = [
  new Extend.Admin()
    // Regular `function` (NOT arrow) so `this` binds to the AdminPage when core
    // calls buildSettingComponent(entry) → entry.call(this); that gives us
    // this.setting(key), the Save-integrated bidi stream.
    .customSetting(function (this: any) {
      return FontPicker.component({
        slot: 'body',
        stream: this.setting(KEY + 'body_font'),
        label: t('body_font_label'),
        help: t('body_font_help', { a: fontsLibraryLink() }),
        heading: false,
      });
    }, 20)
    .customSetting(function (this: any) {
      return FontPicker.component({
        slot: 'heading',
        stream: this.setting(KEY + 'heading_font'),
        label: t('heading_font_label'),
        help: t('heading_font_help'),
        heading: true,
      });
    }, 10),
];
