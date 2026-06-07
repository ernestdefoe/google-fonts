import app from 'flarum/admin/app';
import Extend from 'flarum/common/extenders';
import FontPicker from './components/FontPicker';

const KEY = 'ernestdefoe-google-fonts.';

const t = (k: string) => app.translator.trans('ernestdefoe-google-fonts.admin.' + k);

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
        help: t('body_font_help'),
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
