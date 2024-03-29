<?php

namespace MunicipioElasticsearch\Query;

use ElasticPress\Elasticsearch;
use ElasticPress\Indexables;

class Query {
  private $_indices = [];
  public $results = [];
  public $aggregations = [];
  public $highlight = [];
  public $resultCount = 0;

  public function __construct() {
    $this->_indices = get_field("query_indices", "options");

    add_action("ep_skip_query_integration", [
      $this,
      "ep_skip_query_integration",
    ]);

    add_filter(
      "ep_get_hits_from_query",
      [$this, "ep_get_hits_from_query"],
      10,
      2
    );
  }

  public function ep_skip_query_integration() {
    return true;
  }

  public function ep_get_hits_from_query($hits, $response) {
    if (empty($this->aggregations) && !empty($response["aggregations"])) {
      $this->aggregations = $response["aggregations"];
    }

    if (empty($this->highlight) && !empty($response["hits"]["hits"])) {
      foreach ($response["hits"]["hits"] as $nr => $hit) {
        if (!empty($hit["highlight"])) {
          $this->highlight[$nr] = $hit["highlight"];
        }
      }
    }

    return $hits;
  }

  public function search(
    $searchQuery = null,
    $postType = null,
    $from = 0,
    $resultsPerPage = 10,
    $sort = null,
    $sortOrder = "desc",
    $indices = null,
    $allowEmptySearch = false
  ) {
    if (!empty($indices)) {
      $index_query = implode(",", $indices);
    } elseif (!empty($this->_indices)) {
      $index_query = implode(",", $this->_indices);
    }

    $elasticsearch = new Elasticsearch();

    $query = [
      "from" => $from,
      "size" => $resultsPerPage,
      "sort" => [
        [
          "_score" => [
            "order" => $sortOrder,
          ],
        ],
      ],
    ];

    if ($sort !== null) {
      $sortKey = $sort;

      if ($sortKey === "post_title") {
        $sortKey .= ".raw";
      }

      $query["sort"] = [
        $sortKey => [
          'order' => $sortOrder
        ]
      ];
    }

    if ($searchQuery !== "" || ($searchQuery === "" && !$allowEmptySearch)) {
      $query["query"] = [
        "function_score" => [
          "query" => [
            "bool" => [
              "must" => [
                [
                  "multi_match" => [
                    "query" => $searchQuery,
                    "fields" => [
                      "post_title^1",
                      "post_excerpt^1",
                      "municipio_title^1",
                      "municipio_content^1",
                      "post_author.display_name^1",
                      "terms.post_tag.name^1",
                      "terms.category.name^1",
                      "attachments.attachment.content^1",
                    ],
                    "boost" => 4,
                    "minimum_should_match" => "100%",
                  ],
                ],
                [
                  "multi_match" => [
                    "query" => $searchQuery,
                    "fields" => [
                      "post_title^1",
                      "post_excerpt^1",
                      "municipio_title^1",
                      "municipio_content^1",
                      "post_author.display_name^1",
                      "terms.post_tag.name^1",
                      "terms.category.name^1",
                      "attachments.attachment.content^1",
                    ],
                    "boost" => 2,
                    "fuzziness" => 0,
                  ],
                ],
                [
                  "multi_match" => [
                    "query" => $searchQuery,
                    "fields" => [
                      "post_title^1",
                      "post_excerpt^1",
                      "municipio_title^1",
                      "municipio_content^1",
                      "post_author.display_name^1",
                      "terms.post_tag.name^1",
                      "terms.category.name^1",
                      "attachments.attachment.content^1",
                    ],
                    "fuzziness" => "AUTO",
                  ],
                ],
              ],
              "should" => [
                [
                  "multi_match" => [
                    "query" => $searchQuery,
                    "type" => "phrase",
                    "fields" => [
                      "post_title^1",
                      "post_excerpt^1",
                      "municipio_title^1",
                      "municipio_content^1",
                      "post_author.display_name^1",
                      "terms.post_tag.name^1",
                      "terms.category.name^1",
                      "attachments.attachment.content^1",
                    ],
                    "boost" => 4,
                  ],
                ],
              ],
            ],
          ],
          "score_mode" => "avg",
          "boost_mode" => "sum",
        ],
      ];
    }

    $query["aggs"] = [
      "post_type" => [
        "terms" => [
          "field" => "post_type.raw",
          "size" => 10000,
        ],
      ],
      "indices" => [
        "terms" => [
          "field" => "_index",
          "size" => 10000,
        ],
      ],
    ];

    // 3. Highlight
    $query["highlight"] = [
      "pre_tags" => ["<mark>"],
      "post_tags" => ["</mark>"],
      "fields" => [
        "post_title" => ["number_of_fragments" => 1],
        "municipio_content" => [
          "no_match_size" => 300,
          "number_of_fragments" => 1,
        ],
        "attachments.attachment.content" => [
          "no_match_size" => 300,
          "number_of_fragments" => 1,
        ],
      ],
    ];

    // 4. Post filter
    if ($postType !== null) {
      $query["post_filter"] = [
        "bool" => ["must" => [["term" => ["post_type.raw" => $postType]]]],
      ];
    }

    // elasticQuery.sort = [];
    // // 5. Sort
    // if (sort !== false) {
    //   let sortObj = {};
    //   let sortKey = sort;
    //   if (sortKey === "post_title") {
    //     sortKey += ".raw";
    //   }
    //   sortObj[sortKey] = {
    //     order: sort_order,
    //   };
    //   elasticQuery.sort.push(sortObj);
    // }
    // elasticQuery.sort.push("_score");

    $data = $elasticsearch->query($index_query, "post", $query, []);

    if (!empty($data["documents"])) {
      $this->results = $data["documents"];
    }
    if (!empty($data["found_documents"])) {
      $this->resultCount = $data["found_documents"];
    }
  }
}
