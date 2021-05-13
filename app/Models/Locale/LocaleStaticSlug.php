<?php

namespace App\Models\Locale;

use App\Models\CMS\Language;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class LocaleStaticSlug extends Model
{
    public static function generateFiles()
    {
        $languages = Language::where('languagecode', '<>', 'en')->get()->toArray();

        $sections = DB::table('locale_sections')->where('group', 1)->get();
        $section_ids = $sections->pluck('id')->toArray();
        if (!$section_ids) {
            return false;
        }

        $slugs = static::selectRaw('slug, value, GROUP_CONCAT(lsl.language_id SEPARATOR "|||") language_ids,
        GROUP_CONCAT(lsl.label_content SEPARATOR "|||") content, section_id')
            ->whereIn('section_id', $section_ids)
            ->leftJoin('locale_static_labels as lsl', 'lsl.slug_id', '=', 'locale_static_slugs.id')
            ->groupBy('locale_static_slugs.id')->get();

        $lang_path = base_path() . '/resources/lang/';
        foreach ($sections as $section) {

            $section_slugs = $slugs->where('section_id', $section->id);
            $content['en'] = "<?php\n\nreturn\n[\n";
            foreach ($languages as $language) {
                $content[$language['languagecode']] = "<?php\n\nreturn\n[\n";
            }
            
            foreach ($section_slugs as $row) {
                $content['en'] .= "\t'" . $row->slug . "' => '" . addslashes($row->value) . "',\n";
                $language_ids = explode('|||', $row->language_ids);
                $language_content = explode('|||', $row->content);
                foreach ($languages as $language) {
                    $key = array_search($language['id'], $language_ids);
                    $value = ($key !== false && !empty($language_content[$key])) ? $language_content[$key] : $row->value;
                    $content[$language['languagecode']] .= "\t'" . $row->slug . "' => '" . addslashes($value) . "',\n";
                }
            }
            $content['en'] .= "];";
            file_put_contents($lang_path . 'en/' . $section->locale . '.php', $content['en']);
            foreach ($languages as $language) {
                $content[$language['languagecode']] .= "];";
                file_put_contents($lang_path . $language['languagecode'] . '/' . $section->locale . '.php', $content[$language['languagecode']]);
            }
        }

    }

}
