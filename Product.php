<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable=[
        'name', 'slug', 'sku', 'photo', 'm_price', 'm_discount', 'r_price', 'r_discount',
        'm_quantity','r_quantity', 'size','o_price','o_discount', 'o_quantity', 'desc',
        'active','featured',
        'meta_title', 'meta_keyword', 'category_id', 'maker_id'
    ];

    public function trash()
    {
        $destination = public_path('uploads/product');
        foreach (json_decode($this->photo,true) as $image)
        {
            if (is_file($destination . "/{$image}")) {
                @unlink($destination . "/{$image}");
            }
        }
        $this->delete();
    }

    public function colors()
    {
        return $this->belongsToMany(Color::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function comments()
    {
        return $this->hasMany(CommentProduct::class);
    }

    public function rate()
    {
        if ($this->comments()->count())
            return $this->comments()->pluck('rate')->sum() / $this->comments()->count();
        return 0;
    }


}
