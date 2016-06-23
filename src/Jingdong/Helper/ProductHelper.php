<?php

namespace Jingdong\Helper;

use Jingdong\Request\NewWareBaseproductGetRequest;
use Jingdong\Request\WarePriceGetRequest;
use Jingdong\Request\WareProductimageGetRequest;
use Jingdong\Request\WareSearchRequest;
use Jingdong\JdClient;

class ProductHelper
{
    // The JdClient instance.
    private $client;

    function __construct($appKey, $appSecret)
    {
        $this->client = new JdClient($appKey, $appSecret);
    }

    /**
     * Search products.
     *
     * @param string $q
     * @return array
     */
    public function search($q)
    {
        if (trim($q) != '') {
            // get data
            $request = new WareSearchRequest();
            $request->setKey(urlencode(mb_convert_encoding($q, "GBK")));
            $results = $this->get($request);

            // data processing
            if (isset($results['data'])) {
                $results = $results['data'];

                if (isset($results->Paragraph)) {
                    $results = $results->Paragraph;

                    foreach ($results as $index => $result) {
                        $photo = $result->Content->imageurl;
                        if ($photo == null)
                            $photo = '/images/photo-placeholder.svg';
                        $results[$index] = [
                            'id' => $result->wareid,
                            'photo' => $photo
                        ];

                        // Get product's brothers data if exists.
                        if (isset($result->SlaveWare)) {
                            foreach ($result->SlaveWare as $brother) {
                                $results[$index]['brothersId'][] = $brother->wareid;
                                $results[$index]['brothersPhoto'][] = $brother->Content->imageurl;
                            }
                            // Unique photos
                            $results[$index]['brothersPhoto'] = array_values(array_unique(array_diff($results[$index]['brothersPhoto'], [$photo])));
                        }
                    }

                    return [
                        'data' => [
                            'resultCount' => count($results),
                            'results' => $results
                        ]
                    ];
                }
            }
        }
        return [
            'data' => [
                'resultCount' => 0,
                'results' => []
            ]
        ];
    }

    /**
     * Get Products information. Include brand, name and store.
     *
     * @param array $productsId
     * @return array
     */
    public function information(array $productsId)
    {
        $results = [];

        if ($productsId != []) {
            $request = new NewWareBaseproductGetRequest();
            $request->setBasefields('skuId,ebrand,cbrand,pname,name,shopName');

            // Get data.
            $data = [];
            foreach (array_chunk($productsId, 10) as $chunk) {
                $request->setIds(implode(',', $chunk));
                $temp = $this->get($request);

                if (isset($temp['data']))
                    $data = array_merge($data, $temp['data']->listproductbase_result);
            }

            // Data processing
            foreach ($data as $result) {
                // brand
                $brand = isset($result->ebrand) ? $this->mbCaseTitle($this->removeSpecialChars($result->ebrand)) : '';
                $brand_cn = isset($result->cbrand) ? $this->mbCaseTitle($this->removeSpecialChars($result->cbrand)) : '';
                if ($brand != $brand_cn)
                    $brand .= $brand_cn;
                $brand_en = $this->getLanguageString($brand, 'English');
                $brand_cn = $this->getLanguageString($brand, 'Chinese');

                // name
                $name = isset($result->pname) ? $result->pname : (isset($result->name) ? $result->name : '');
                $name = $this->removeSpecialChars($name, 'light');
                // Remove brand from name.
                $name = $this->removeSpecialChars($name, [$brand_en, $brand_cn]);
                // Filter meaningless words for name.
                $name = $this->removeSpecialChars($name, [
                    '新老包装',
                    '包装',
                    '随机',
                    '派送',
                    '发货',
                    '颜色',
                    '发放',
                    '特惠装',
                    '特惠',
                    '性价比',
                    '内存版',
                    '大内存',
                    '16GB',
                    '32GB',
                    '64GB',
                    '8GB',
                    '128GB',
                    '16G',
                    '32G',
                    '64G',
                    '8G',
                    '128G',
                    '2GB',
                    '3GB',
                    '4GB',
                    '5GB',
                    'RAM',
                    'ROM',
                    '移动4G',
                    '移动',
                    '联通4G',
                    '联通',
                    '双网通版',
                    '双网通',
                    '双通',
                    '全网通',
                    '电信4G',
                    '电信',
                    '双4G',
                    '双卡双待',
                    '包邮',
                    '免邮',
                    '已停产',
                    '指纹解锁',
                    '0元购机'
                ]);

                // brand
                $brand = $brand_en;
                $brand .= $brand ? ($brand_cn ? '/' . $brand_cn : '') : $brand_cn;

                // store
                $store = isset($result->shopName) ? $result->shopName : '京东自营店';
                if ($brand == '' && $name == '')
                    $store = '';

                $results[$result->skuId] = [
                    'brand' => $brand,
                    'name' => $name,
                    'store' => $store
                ];
            }
        }

        return $results;
    }

    /**
     * Get products' photos.
     *
     * @param array $productsId
     * @return array
     */
    public function photos(array $productsId)
    {
        $results = [];

        if ($productsId != []) {
            $request = new WareProductimageGetRequest();

            $data = [];
            foreach (array_chunk($productsId, 10) as $chunk) {
                // Get data.
                $request->setSkuId(implode(',', $chunk));
                $temp = $this->get($request);

                if (isset($temp['data']))
                    $data = array_merge($data, $temp['data']->image_path_list);
            }

            // Data processing.
            foreach ($data as $result) {
                $photos = [];
                foreach ($result->image_list as $photo) {
                    $photo = $photo->path;
                    // Change "http://aaa.bbb.com/ccc/ddd/eee.jpg" to "ddd/eee.jpg", and add to photos.
                    $photos[] = substr($photo, stripos($photo, '/', stripos($photo, ".com") + 5) + 1);
                }

                $results[$result->sku_id] = $photos;
            }
        }

        return $results;
    }

    /**
     * Get product minim price.
     *
     * @param array $id
     * @return int|mixed
     */
    public function minPrice(array $id)
    {
        foreach ($id as $item) {
            $price = $this->price($item);
            if ($price != 0 && $price != -1)
                $prices[] = $price;
        }
        return isset($prices) ? min($prices) : -1;
    }

    /**
     * Get product price.
     *
     * @param string $id
     * @return int
     */
    public function price($id)
    {
        if ($id != '') {
            $request = new WarePriceGetRequest();
            $request->setSkuId("J_" . $id);
            $results = $this->get($request);

            if (isset($results['data'])) {
                $results = $results['data'];
                return intval(round(floatval($results->price_changes[0]->price)));
            }
        }

        return -1;
    }

    /**
     * Get data from Jingdong.
     *
     * @param Jingdong/Request $request
     * @return array
     */
    private function get($request)
    {
        $response = $this->client->execute($request);

        if ($response->code == '0')
            return ['data' => $response];

        return [
            'errors' => [
                'code' => 'UNKNOWN'
            ]
        ];
    }

    /**
     * Get the language string.
     *
     * @param string $string
     * @param $language
     * @return mixed|string
     */
    private function getLanguageString($string = '', $language)
    {
        switch ($language) {
            case 'English':
                return preg_replace(array('/[\p{Han}？]/u', '/(\s)+/'), array('', '$1'), $string);
            case 'Chinese':
                return preg_replace(array('/[^\p{Han}？]/u', '/(\s)+/'), array('', '$1'), $string);
            default:
                return $string;
        }
    }

    /**
     * Remove special chars for a string.
     *
     * @param string $string
     * @param string $preg
     * @return string
     */
    private function removeSpecialChars($string = '', $preg = 'all')
    {
        if (count($string) > 0) {
            $defaultPreg = [
                '\\',
                '(',
                ')',
                '（',
                '）',
                '[',
                ']',
                '［',
                '］',
                '【',
                '】',
                ',',
                '、',
                '+',
                '＋',
            ];

            switch ($preg) {
                case 'light':
                    $preg = $defaultPreg;
                    break;
                case 'all':
                    $preg = $defaultPreg;
                    $preg[] = '/';
                    $preg[] = '／';
                    break;
            }

            return trim(str_ireplace('  ', ' ', str_ireplace($preg, '', $string)));
        }

        return $string;
    }

    /**
     * Performs case folding on a string, converted in the way specified by MB_CASE_TITLE.
     *
     * @param string $string
     * @return mixed|string
     */
    private function mbCaseTitle($string = '')
    {
        if (count($string) > 0) {
            return mb_convert_case($string, MB_CASE_TITLE, "UTF-8");
        }
        return $string;
    }

}
