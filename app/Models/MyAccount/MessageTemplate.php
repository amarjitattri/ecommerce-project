<?php

namespace App\Models\MyAccount;

use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{

    public function scopeIsParent($query, $alias = 'message_templates')
    {
        return $query->whereNull($alias . '.parent_id');
    }

    public function scopeLanguageJoin($query, $alias = 'message_templates')
    {
        $query->when(session('language.languagecode') != 'en', function ($q) use ($alias) {
            $q->selectRaw('IF(lc.content = "" OR lc.content IS NULL, '. $alias .'.content, lc.content) as content,
            IF(lc.subject = "" OR lc.subject IS NULL, '. $alias .'.subject, lc.subject) as subject')
                ->leftJoin('message_templates as lc', function ($qr) use ($alias) {
                    $qr->on('lc.parent_id', '=', $alias . '.id')
                        ->where('lc.language_id', session('language.id'));
                });
        });
    }

}
