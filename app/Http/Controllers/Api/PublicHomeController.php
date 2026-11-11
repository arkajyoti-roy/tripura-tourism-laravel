<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicHomeController extends Controller
{
    private function getImageUrl($folder, $filename)
    {
        if (!$filename) return null;
        return asset("images/{$folder}/{$filename}");
    }

    private function getTourImageUrl($tour, $imageField)
    {
        if (!isset($tour->$imageField) || !$tour->$imageField) return null;
        $folder = isset($tour->folder) && $tour->folder ? 'tour/' . $tour->folder : 'tour';
        return asset("images/{$folder}/{$tour->$imageField}");
    }

    

    public function sliders()
    {
        $sliders = DB::table('slider')->orderBy('id', 'desc')->get()->map(function ($slider) {
            $slider->image_url = $this->getImageUrl('slider', $slider->image ?? null);
            return $slider;
        });
        return response()->json($sliders);
    }

    public function whatsNew()
    {
        $sixtyDaysAgo = time() - (60 * 24 * 60 * 60);

        $documents = DB::table('documents')
            ->where('whatsnew', '1')
            ->orderBy('docid', 'desc')
            ->get()
            ->filter(function ($doc) use ($sixtyDaysAgo) {
                
                if (!empty($doc->closeDate)) {
                    $closeTime = is_numeric($doc->closeDate) ? (int)$doc->closeDate : strtotime($doc->closeDate);
                    if ($closeTime && time() > $closeTime) {
                        return false;
                    }
                }

                $uploadTime = null;
                if (!empty($doc->updateDate)) {
                    $uploadTime = is_numeric($doc->updateDate) ? (int)$doc->updateDate : strtotime($doc->updateDate);
                }
                if (!$uploadTime && !empty($doc->openDate)) {
                    $uploadTime = is_numeric($doc->openDate) ? (int)$doc->openDate : strtotime($doc->openDate);
                }
                return $uploadTime && ($uploadTime >= $sixtyDaysAgo);
            })
            ->unique(function ($doc) {
                return trim(strtolower($doc->des));
            })
            ->map(function ($doc) {
                $doc->file_url = $this->getImageUrl('documents', $doc->file ?? null);
                return $doc;
            })
            ->values();

        return response()->json([
            'documents' => $documents,
            'notices' => []
        ]);
    }

    

    public function categories()
    {
        $categories = DB::table('category')->orderBy('id', 'desc')->get()->map(function ($cat) {
            $cat->image_url = $this->getImageUrl('slider', $cat->image ?? null); 
            return $cat;
        });
        return response()->json($categories);
    }

    

    public function destinations()
    {
        $destinations = DB::table('tour')
            ->where('destype', '1')
            ->orderBy('id', 'desc')
            ->get()->map(function ($tour) {
                $tour->image_url = $this->getTourImageUrl($tour, 'image');
                $tour->image2_url = $this->getTourImageUrl($tour, 'image2');
                $tour->image3_url = $this->getTourImageUrl($tour, 'image3');
                $tour->image4_url = $this->getTourImageUrl($tour, 'image4');
                $tour->image5_url = $this->getTourImageUrl($tour, 'image5');
                $tour->image6_url = $this->getTourImageUrl($tour, 'image6');
                $tour->image7_url = $this->getTourImageUrl($tour, 'image7');
                return $tour;
            });
        
        return response()->json($destinations);
    }

    

    public function festivals()
    {
        $festivals = DB::table('tour')
            ->where('destype', '2')
            ->orderBy('id', 'desc')
            ->get()->map(function ($tour) {
                $tour->image_url = $this->getTourImageUrl($tour, 'image');
                $tour->image2_url = $this->getTourImageUrl($tour, 'image2');
                $tour->image3_url = $this->getTourImageUrl($tour, 'image3');
                $tour->image4_url = $this->getTourImageUrl($tour, 'image4');
                $tour->image5_url = $this->getTourImageUrl($tour, 'image5');
                $tour->image6_url = $this->getTourImageUrl($tour, 'image6');
                $tour->image7_url = $this->getTourImageUrl($tour, 'image7');
                return $tour;
            });
        
        return response()->json($festivals);
    }

    

    public function featuredTours()
    {
        $tours = DB::table('tour')
            ->where('show_on_homepage', 1)
            ->orderBy('id', 'desc')
            ->get()->map(function ($tour) {
                $tour->image_url = $this->getTourImageUrl($tour, 'image');
                $tour->image2_url = $this->getTourImageUrl($tour, 'image2');
                $tour->image3_url = $this->getTourImageUrl($tour, 'image3');
                $tour->image4_url = $this->getTourImageUrl($tour, 'image4');
                $tour->image5_url = $this->getTourImageUrl($tour, 'image5');
                $tour->image6_url = $this->getTourImageUrl($tour, 'image6');
                $tour->image7_url = $this->getTourImageUrl($tour, 'image7');
                return $tour;
            });
        return response()->json($tours);
    }

    

    public function discover()
    {
        $tours = DB::table('tour')->orderBy('id', 'desc')->limit(15)->get()->map(function ($tour) {
            $tour->image_url = $this->getTourImageUrl($tour, 'image');
            $tour->image2_url = $this->getTourImageUrl($tour, 'image2');
            $tour->image3_url = $this->getTourImageUrl($tour, 'image3');
            $tour->image4_url = $this->getTourImageUrl($tour, 'image4');
            $tour->image5_url = $this->getTourImageUrl($tour, 'image5');
            $tour->image6_url = $this->getTourImageUrl($tour, 'image6');
            $tour->image7_url = $this->getTourImageUrl($tour, 'image7');
            return $tour;
        });
        return response()->json($tours);
    }

    

    public function accommodations(Request $request)
    {
        $query = \App\Models\Accommodation::query()
            ->where(function ($q) {
                // Govt (1) and Leaseout (3) are always shown.
                // Private (2) and Homestays (4) must be active and have valid license.
                $q->whereNotIn('type', ['2', '4'])
                  ->orWhere(function ($sub) {
                      $sub->whereIn('type', ['2', '4'])->active();
                  });
            });

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('name_of_lessee', 'like', "%{$search}%");
            });
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('pageSize', 12))));
            $paginated = $query->orderBy('displayorder', 'asc')->orderBy('aid', 'desc')->paginate($perPage);

            $paginated->getCollection()->transform(function ($acc) {
                $acc->id = $acc->aid;
                $folder = ($acc->type == '4') ? 'accommodation/homestay' : 'accommodation';
                $acc->image_url = $this->getImageUrl($folder, $acc->image ?? null);
                $acc->image2_url = $this->getImageUrl($folder, $acc->image2 ?? null);
                $acc->image3_url = $this->getImageUrl($folder, $acc->image3 ?? null);
                return $acc;
            });

            return response()->json($paginated);
        }

        $accommodations = $query->orderBy('displayorder', 'asc')->orderBy('aid', 'desc')->get()->map(function ($acc) {
            $acc->id = $acc->aid;
            $folder = ($acc->type == '4') ? 'accommodation/homestay' : 'accommodation';
            $acc->image_url = $this->getImageUrl($folder, $acc->image ?? null);
            $acc->image2_url = $this->getImageUrl($folder, $acc->image2 ?? null);
            $acc->image3_url = $this->getImageUrl($folder, $acc->image3 ?? null);
            return $acc;
        });

        return response()->json($accommodations);
    }

    public function govtAccommodations(Request $request)
    {
        $query = \App\Models\Accommodation::query()->where('type', '1');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%");
            });
        }

        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('pageSize', 12))));
            $paginated = $query->orderBy('displayorder', 'asc')->paginate($perPage);
            $paginated->getCollection()->transform(function ($acc) {
                $acc->id = $acc->aid;
                $acc->image_url = $this->getImageUrl('accommodation', $acc->image ?? null);
                $acc->image2_url = $this->getImageUrl('accommodation', $acc->image2 ?? null);
                $acc->image3_url = $this->getImageUrl('accommodation', $acc->image3 ?? null);
                return $acc;
            });
            return response()->json($paginated);
        }

        $accommodations = $query->orderBy('displayorder', 'asc')->get()->map(function ($acc) {
            $acc->id = $acc->aid;
            $acc->image_url = $this->getImageUrl('accommodation', $acc->image ?? null);
            $acc->image2_url = $this->getImageUrl('accommodation', $acc->image2 ?? null);
            $acc->image3_url = $this->getImageUrl('accommodation', $acc->image3 ?? null);
            return $acc;
        });
        return response()->json($accommodations);
    }

    public function privateAccommodations(Request $request)
    {
        // Private Accommodation (Hotels / Resorts): must be active & license valid
        $query = \App\Models\Accommodation::query()
            ->where('type', '2')
            ->active();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%");
            });
        }

        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('pageSize', 12))));
            $paginated = $query->orderBy('displayorder', 'asc')->paginate($perPage);
            $paginated->getCollection()->transform(function ($acc) {
                $acc->id = $acc->aid;
                $acc->image_url = $this->getImageUrl('accommodation', $acc->image ?? null);
                $acc->image2_url = $this->getImageUrl('accommodation', $acc->image2 ?? null);
                $acc->image3_url = $this->getImageUrl('accommodation', $acc->image3 ?? null);
                return $acc;
            });
            return response()->json($paginated);
        }

        $accommodations = $query->orderBy('displayorder', 'asc')->get()->map(function ($acc) {
            $acc->id = $acc->aid;
            $acc->image_url = $this->getImageUrl('accommodation', $acc->image ?? null);
            $acc->image2_url = $this->getImageUrl('accommodation', $acc->image2 ?? null);
            $acc->image3_url = $this->getImageUrl('accommodation', $acc->image3 ?? null);
            return $acc;
        });
        return response()->json($accommodations);
    }

    public function leaseoutAccommodations(Request $request)
    {
        $query = \App\Models\Accommodation::query()->where('type', '3');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('name_of_lessee', 'like', "%{$search}%");
            });
        }

        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('pageSize', 12))));
            $paginated = $query->orderBy('aid', 'desc')->paginate($perPage);
            $paginated->getCollection()->transform(function ($acc) {
                $acc->id = $acc->aid;
                $acc->image_url = $this->getImageUrl('accommodation', $acc->image ?? null);
                $acc->image2_url = $this->getImageUrl('accommodation', $acc->image2 ?? null);
                $acc->image3_url = $this->getImageUrl('accommodation', $acc->image3 ?? null);
                return $acc;
            });
            return response()->json($paginated);
        }

        $accommodations = $query->orderBy('aid', 'desc')->get()->map(function ($acc) {
            $acc->id = $acc->aid;
            $acc->image_url = $this->getImageUrl('accommodation', $acc->image ?? null);
            $acc->image2_url = $this->getImageUrl('accommodation', $acc->image2 ?? null);
            $acc->image3_url = $this->getImageUrl('accommodation', $acc->image3 ?? null);
            return $acc;
        });
        return response()->json($accommodations);
    }

    public function homestaysAccommodations(Request $request)
    {
        // Homestays: must be active & license valid
        $query = \App\Models\Accommodation::query()
            ->where('type', '4')
            ->active();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('name_of_lessee', 'like', "%{$search}%")
                    ->orWhere('contact', 'like', "%{$search}%");
            });
        }

        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('pageSize', 12))));
            $paginated = $query->orderBy('displayorder', 'asc')->paginate($perPage);
            $paginated->getCollection()->transform(function ($acc) {
                $acc->id = $acc->aid;
                $acc->image_url = $this->getImageUrl('accommodation/homestay', $acc->image ?? null);
                $acc->image2_url = $this->getImageUrl('accommodation/homestay', $acc->image2 ?? null);
                $acc->image3_url = $this->getImageUrl('accommodation/homestay', $acc->image3 ?? null);
                return $acc;
            });
            return response()->json($paginated);
        }

        $accommodations = $query->orderBy('displayorder', 'asc')->get()->map(function ($acc) {
            $acc->id = $acc->aid;
            $acc->image_url = $this->getImageUrl('accommodation/homestay', $acc->image ?? null);
            $acc->image2_url = $this->getImageUrl('accommodation/homestay', $acc->image2 ?? null);
            $acc->image3_url = $this->getImageUrl('accommodation/homestay', $acc->image3 ?? null);
            return $acc;
        });
        return response()->json($accommodations);
    }

    

    public function agents(Request $request)
    {
        $today = date('Y-m-d');
        $dateExpr = "(CASE WHEN validDate REGEXP '^[0-9]{2}[-/][0-9]{2}[-/][0-9]{4}$' ".
            "THEN STR_TO_DATE(REPLACE(validDate, '/', '-'), '%d-%m-%Y') ".
            "ELSE STR_TO_DATE(REPLACE(validDate, '/', '-'), '%Y-%m-%d') END)";

        $query = \App\Models\Agent::query()
            ->where(function ($q) use ($today, $dateExpr) {
                // Must be active AND validDate >= today
                $q->where(function ($sub) use ($today, $dateExpr) {
                    $sub->where('is_active', true)
                        ->orWhere(function ($s) use ($today, $dateExpr) {
                            $s->whereNull('is_active')
                              ->where(function ($d) use ($today, $dateExpr) {
                                  $d->whereNull('validDate')
                                    ->orWhere('validDate', '')
                                    ->orWhereRaw("{$dateExpr} >= ?", [$today]);
                              });
                        });
                })->where(function ($sub) use ($today, $dateExpr) {
                    $sub->whereNull('validDate')
                        ->orWhere('validDate', '')
                        ->orWhereRaw("{$dateExpr} >= ?", [$today]);
                });
            });

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('pageSize', 12))));
            return response()->json($query->orderBy('displayorder', 'asc')->paginate($perPage));
        }

        return response()->json($query->orderBy('displayorder', 'asc')->get());
    }

    public function guides(Request $request)
    {
        $today = date('Y-m-d');
        $dateExpr = "(CASE WHEN validDate REGEXP '^[0-9]{2}[-/][0-9]{2}[-/][0-9]{4}$' ".
            "THEN STR_TO_DATE(REPLACE(validDate, '/', '-'), '%d-%m-%Y') ".
            "ELSE STR_TO_DATE(REPLACE(validDate, '/', '-'), '%Y-%m-%d') END)";

        $query = \App\Models\Guide::query()
            ->where(function ($q) use ($today, $dateExpr) {
                // Must be active AND validDate >= today
                $q->where(function ($sub) use ($today, $dateExpr) {
                    $sub->where('is_active', true)
                        ->orWhere(function ($s) use ($today, $dateExpr) {
                            $s->whereNull('is_active')
                              ->where(function ($d) use ($today, $dateExpr) {
                                  $d->whereNull('validDate')
                                    ->orWhere('validDate', '')
                                    ->orWhereRaw("{$dateExpr} >= ?", [$today]);
                              });
                        });
                })->where(function ($sub) use ($today, $dateExpr) {
                    $sub->whereNull('validDate')
                        ->orWhere('validDate', '')
                        ->orWhereRaw("{$dateExpr} >= ?", [$today]);
                });
            });

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('language', 'like', "%{$search}%")
                    ->orWhere('spot', 'like', "%{$search}%");
            });
        }

        if ($request->has('page') || $request->has('per_page') || $request->has('pageSize')) {
            $perPage = max(1, min(100, (int) $request->input('per_page', $request->input('pageSize', 12))));
            $paginated = $query->orderBy('displayorder', 'asc')->paginate($perPage);
            $paginated->getCollection()->transform(function ($guide) {
                $guide->image_url = $guide->image ? asset('images/guides/' . $guide->image) : null;
                return $guide;
            });
            return response()->json($paginated);
        }

        $guides = $query->orderBy('displayorder', 'asc')->get()->map(function ($guide) {
            $guide->image_url = $guide->image ? asset('images/guides/' . $guide->image) : null;
            return $guide;
        });

        return response()->json($guides);
    }

    public function gallery()
    {
        $photos = DB::table('photo')->orderBy('id', 'desc')->get()->map(function ($photo) {
            
            $photo->image_url = $this->getImageUrl('galleries/' . $photo->photo_div, $photo->image ?? null);
            return $photo;
        });
        
        $videos = DB::table('video')->orderBy('id', 'desc')->get()->map(function ($video) {
            $video->thumbnail_url = $this->getImageUrl('galleries', $video->image ?? null);
            $video->video_url = $this->getImageUrl('galleries', $video->video ?? null);
            return $video;
        });
        
        return response()->json([
            'photos' => $photos,
            'videos' => $videos
        ]);
    }

    public function photos()
    {
        $photos = DB::table('photo')->orderBy('id', 'desc')->get()->map(function ($photo) {
            $photo->image_url = $this->getImageUrl('galleries/' . $photo->photo_div, $photo->image ?? null);
            return $photo;
        });
        return response()->json($photos);
    }

    public function adventures()
    {
        $adventures = DB::table('adventures')->orderBy('id', 'desc')->get()->map(function ($adventure) {
            $adventure->image_url = $this->getImageUrl('adventure', $adventure->image ?? null);
            return $adventure;
        });
        return response()->json($adventures);
    }

    public function videos()
    {
        $videos = DB::table('video')->orderBy('id', 'desc')->get();
        return response()->json($videos);
    }
}
