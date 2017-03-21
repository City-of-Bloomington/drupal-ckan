<?php
/**
 * Replaces ckan dataset syntax with an HTML table
 *
 * Users specify the CKAN dataset they want to embed as:
 * {ckan_table:$resource_id}
 * Example: {ckan_table:a38d25a4-7a50-4984-9ac9-60c49858a3a3}
 *
 * @copyright 2017 City of Bloomington, Indiana
 * @license http://www.gnu.org/licenses/agpl.txt GNU/AGPL, see LICENSE.txt
 *
 * @Filter(
 *     id = "filter_resourcetable",
 *     title = "CKAN Resource Table",
 *     description = "Renders data from a CKAN Resource as an HTML table",
 *     type = Drupal\filter\Plugin\FilterInterface::TYPE_MARKUP_LANGUAGE
 * )
 */
namespace Drupal\ckan\Plugin\Filter;

use Drupal\Core\Site\Settings;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;

class FilterResourceTable extends FilterBase
{
    public function process($text, $lang)
    {
        $syntax = '/{ckan_table:([0-9a-f\-]+)}/';

        $config = \Drupal::config('ckan.settings');
        $url    = $config->get('ckan_url');

        if (preg_match($syntax, $text, $matches)) {
            $resource_id = $matches[1];

            $url.= '/api/action/datastore_search?resource_id='.$resource_id;
            $client   = \Drupal::httpClient();
            $response = $client->get($url);
            $data     = json_decode($response->getBody());

            if (count($data) && $data->success) {
                $html = '<table>';
                // Parse all the fieldnames
                $fields = [];
                foreach ($data->result->fields as $f) {
                    if ($f->id != '_id') { $fields[] = $f->id; }
                }
                // Render the fieldnames as table headers
                $html.= '<thead><tr>';
                foreach ($fields as $f) {
                    $f = check_plain($f);
                    $html.= "<th>$f</th>";
                }
                $html.= '</tr></thead>';

                // Render each of the rows as table cells
                $html.= '<tbody>';
                foreach ($data->result->records as $row) {
                    $html.= '<tr>';
                    foreach ($fields as $field) {
                        $value = check_plain($row->$field);
                        $html.= "<td>$value</td>";
                    }
                    $html.= '</tr>';

                }
                $html.= '</tbody></table>';

                $text = preg_replace($syntax, $html, $text);
            }
        }
        return new FilterProcessResult($text);
    }
}
