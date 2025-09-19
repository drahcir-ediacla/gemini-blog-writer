<?php
if (!defined('ABSPATH'))
    exit;


// use MailPoet\Cron\Workers\SendingQueue\Tasks\Newsletter as NewsletterQueueTask;


/**
 * Schedule MailPoet newsletter when a Gemini-generated post is published.
 */
function gemini_schedule_mailpoet_newsletter($new_status, $old_status, $post)
{
    $log_path = __DIR__ . '/mailpoet-debug.txt';
    file_put_contents($log_path, "🔔 Hook triggered: $old_status ➜ $new_status for post ID {$post->ID}\n", FILE_APPEND);

    if (
        $old_status === 'publish' ||
        $new_status !== 'publish' ||
        $post->post_type !== 'post'
    ) {
        file_put_contents($log_path, "⛔ Skipped: Not a new publish or wrong post type.\n", FILE_APPEND);
        file_put_contents($log_path, "ℹ️ Post type: {$post->post_type}\n", FILE_APPEND);
        return;
    }

    if (!get_post_meta($post->ID, '_gemini_generated', true)) {
        file_put_contents($log_path, "⛔ Skipped: Not Gemini-generated.\n", FILE_APPEND);
        return;
    }

    // Defer to shutdown
    add_action('shutdown', function () use ($post, $log_path) {
        wp_schedule_single_event(time() + 15, 'gemini_run_mailpoet_newsletter_send', [$post->ID]);
        file_put_contents($log_path, "⏳ Scheduled newsletter for post ID {$post->ID} in 15s (via shutdown).\n", FILE_APPEND);
    });
}
add_action('transition_post_status', 'gemini_schedule_mailpoet_newsletter', 10, 3);


/**
 * Run MailPoet newsletter send using MailPoet API.
 */
function gemini_run_mailpoet_newsletter_send($post_id)
{
    $log_path = __DIR__ . '/mailpoet-debug.txt';
    file_put_contents($log_path, "=== Starting MailPoet newsletter send job for post ID: {$post_id} ===\n", FILE_APPEND);

    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        file_put_contents($log_path, "❌ Invalid post or not published.\n", FILE_APPEND);
        return;
    }

    if (!get_post_meta($post_id, '_gemini_generated', true)) {
        file_put_contents($log_path, "❌ Post missing _gemini_generated meta flag.\n", FILE_APPEND);
        return;
    }

    if (get_post_meta($post_id, '_gemini_newsletter_sent', true)) {
        file_put_contents($log_path, "❌ Newsletter already sent for this post.\n", FILE_APPEND);
        return;
    }

    try {
        require_once WP_PLUGIN_DIR . '/mailpoet/vendor/autoload.php';
        $container = \MailPoet\DI\ContainerWrapper::getInstance();
        file_put_contents($log_path, "[" . current_time('mysql') . "] ✅ MailPoet container loaded.\n", FILE_APPEND);

        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $content = apply_filters('the_content', $post->post_content);
        $excerpt = has_excerpt($post_id) ? get_the_excerpt($post_id) : wp_trim_words(strip_tags($content), 40);
        $image_url = get_the_post_thumbnail_url($post_id, 'medium');
        $alt_text = $alt_text ?: 'Featured image for ' . get_the_title($post_id);

        $body_array = [
            'content' => [
                'type' => 'container',
                'columnLayout' => false,
                'orientation' => 'vertical',
                'image' => ['src' => null, 'display' => 'scale'],
                'styles' => ['block' => ['backgroundColor' => 'transparent']],
                'blocks' => [
                    [
                        'type' => 'container',
                        'columnLayout' => false,
                        'orientation' => 'horizontal',
                        'image' => ['src' => null, 'display' => 'scale'],
                        'styles' => ['block' => ['backgroundColor' => '#1c2735']],
                        "blocks" => [
                            [
                                "type" => "container",
                                "columnLayout" => false,
                                "orientation" => "vertical",
                                "image" => [
                                    "src" => null,
                                    "display" => "scale"
                                ],
                                "styles" => [
                                    "block" => [
                                        "backgroundColor" => "transparent"
                                    ]
                                ],
                                "blocks" => [
                                    [
                                        "type" => "spacer",
                                        "styles" => [
                                            "block" => [
                                                "backgroundColor" => "transparent",
                                                "height" => "30px"
                                            ]
                                        ]
                                    ],
                                    [
                                        "type" => "image",
                                        "link" => "",
                                        "src" => "https://firstg.co/wp-content/uploads/2024/08/1G_logo_02.png",
                                        "alt" => "",
                                        "fullWidth" => false,
                                        "width" => "130px",
                                        "height" => "448px",
                                        "styles" => [
                                            "block" => [
                                                "textAlign" => "center"
                                            ]
                                        ]
                                    ],
                                    [
                                        "type" => "divider",
                                        "styles" => [
                                            "block" => [
                                                "backgroundColor" => "transparent",
                                                "padding" => "13px",
                                                "borderStyle" => "solid",
                                                "borderWidth" => "3px",
                                                "borderColor" => "#aaaaaa"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        "type" => "container",
                        "columnLayout" => false,
                        "orientation" => "horizontal",
                        "image" => [
                            "src" => null,
                            "display" => "scale"
                        ],
                        "styles" => [
                            "block" => [
                                "backgroundColor" => "#1c2735"
                            ]
                        ],
                        "blocks" => [
                            [
                                "type" => "container",
                                "columnLayout" => false,
                                "orientation" => "vertical",
                                "image" => [
                                    "src" => null,
                                    "display" => "scale"
                                ],
                                "styles" => [
                                    "block" => [
                                        "backgroundColor" => "transparent"
                                    ]
                                ],
                                "blocks" => [
                                    [
                                        "type" => "text",
                                        "text" => "<h1 style=\"text-align: left;\" data-post-id=\"2396\"><span style=\"color: #ffffff;\">{$title}</span></h1>"
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        "type" => "container",
                        "columnLayout" => false,
                        "orientation" => "horizontal",
                        "image" => [
                            "src" => null,
                            "display" => "scale"
                        ],
                        "styles" => [
                            "block" => [
                                "backgroundColor" => "#1c2735"
                            ]
                        ],
                        "blocks" => [
                            [
                                "type" => "container",
                                "columnLayout" => false,
                                "orientation" => "vertical",
                                "image" => [
                                    "src" => null,
                                    "display" => "scale"
                                ],
                                "styles" => [
                                    "block" => [
                                        "backgroundColor" => "transparent"
                                    ]
                                ],
                                'blocks' => [
                                    [
                                        'type' => 'text',
                                        'text' => "<p class=\"mailpoet_wp_post\"><span style=\"color: #ffffff;\">{$excerpt}</span></p>\n"
                                    ],
                                    [
                                        "type" => "button",
                                        "text" => "Read more",
                                        "url" => $permalink,
                                        "styles" => [
                                            "block" => [
                                                "backgroundColor" => "#c3317d",
                                                "borderColor" => "#c3317d",
                                                "borderWidth" => "1px",
                                                "borderRadius" => "16px",
                                                "borderStyle" => "solid",
                                                "width" => "126px",
                                                "lineHeight" => "28px",
                                                "fontColor" => "#ffffff",
                                                "fontFamily" => "Verdana",
                                                "fontSize" => "16px",
                                                "fontWeight" => "normal",
                                                "textAlign" => "left"
                                            ]
                                        ]
                                    ],
                                    [
                                        'type' => 'spacer',
                                        'styles' => [
                                            'block' => [
                                                'backgroundColor' => 'transparent',
                                                'height' => '40px'
                                            ]
                                        ]
                                    ]
                                ]

                            ],
                            [
                                "type" => "container",
                                "columnLayout" => false,
                                "orientation" => "vertical",
                                "image" => [
                                    "src" => null,
                                    "display" => "scale"
                                ],
                                "styles" => [
                                    "block" => [
                                        "backgroundColor" => "transparent"
                                    ]
                                ],
                                "blocks" => [
                                    [
                                        "type" => "image",
                                        "link" => "{$permalink}",
                                        "src" => "{$image_url}",
                                        "alt" => "{$alt_text}",
                                        "fullWidth" => false,
                                        "width" => 1024,
                                        "height" => 1024,
                                        "styles" => [
                                            "block" => [
                                                "textAlign" => "center"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        "type" => "container",
                        "columnLayout" => false,
                        "orientation" => "horizontal",
                        "image" => [
                            "src" => null,
                            "display" => "scale"
                        ],
                        "styles" => [
                            "block" => [
                                "backgroundColor" => "#c3317d"
                            ]
                        ],
                        "blocks" => [
                            [
                                "type" => "container",
                                "columnLayout" => false,
                                "orientation" => "vertical",
                                "image" => [
                                    "src" => null,
                                    "display" => "scale"
                                ],
                                "styles" => [
                                    "block" => [
                                        "backgroundColor" => "transparent"
                                    ]
                                ],
                                "blocks" => [
                                    [
                                        "type" => "text",
                                        "text" => "<p style=\"text-align: center;\"><span style=\"color: #ffffff;\">Follow First G on Facebook</span></p>"
                                    ],
                                    [
                                        "type" => "social",
                                        "iconSet" => "official-white",
                                        "styles" => [
                                            "block" => [
                                                "textAlign" => "center"
                                            ]
                                        ],
                                        "icons" => [
                                            [
                                                "type" => "socialIcon",
                                                "iconType" => "facebook",
                                                "link" => "https://www.facebook.com/firstgoutsourcinginc/",
                                                "image" => "https://firstg.co/wp-content/plugins/mailpoet/assets/img/newsletter_editor/social-icons/12-official-white/Facebook.png",
                                                "height" => "32px",
                                                "width" => "32px",
                                                "text" => "Facebook"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        "type" => "container",
                        "columnLayout" => false,
                        "orientation" => "horizontal",
                        "image" => [
                            "src" => null,
                            "display" => "scale"
                        ],
                        "styles" => [
                            "block" => [
                                "backgroundColor" => "#f8f8f8"
                            ]
                        ],
                        "blocks" => [
                            [
                                "type" => "container",
                                "columnLayout" => false,
                                "orientation" => "vertical",
                                "image" => [
                                    "src" => null,
                                    "display" => "scale"
                                ],
                                "styles" => [
                                    "block" => [
                                        "backgroundColor" => "transparent"
                                    ]
                                ],
                                "blocks" => [
                                    [
                                        "type" => "footer",
                                        "text" => "<p><a href=\"[link:subscription_unsubscribe_url]\">Unsubscribe</a> | <a href=\"[link:subscription_manage_url]\">Manage your subscription</a><br><span>Unit 3006 One Corporate Centre Bldg. Julia Vargas Ave. Ortigas Center, San Antonio, Pasig City, 1605&nbsp; Metro Manila, Philippines</span></p>",
                                        "styles" => [
                                            "block" => [
                                                "backgroundColor" => "transparent"
                                            ],
                                            "text" => [
                                                "fontColor" => "#222222",
                                                "fontFamily" => "Arial",
                                                "fontSize" => "12px",
                                                "textAlign" => "center"
                                            ],
                                            "link" => [
                                                "fontColor" => "#c3317d",
                                                "textDecoration" => "underline"
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            'globalStyles' => [
                "text" => [
                    "fontColor" => "#000000",
                    "fontFamily" => "Arial",
                    "fontSize" => "16px",
                    "lineHeight" => "1.6"
                ],
                "h1" => [
                    "fontColor" => "#111111",
                    "fontFamily" => "Trebuchet MS",
                    "fontSize" => "30px",
                    "lineHeight" => "1.6"
                ],
                "h2" => [
                    "fontColor" => "#222222",
                    "fontFamily" => "Trebuchet MS",
                    "fontSize" => "24px",
                    "lineHeight" => "1.6"
                ],
                "h3" => [
                    "fontColor" => "#333333",
                    "fontFamily" => "Trebuchet MS",
                    "fontSize" => "22px",
                    "lineHeight" => "1.6"
                ],
                "link" => [
                    "fontColor" => "#21759b",
                    "textDecoration" => "underline"
                ],
                "wrapper" => [
                    "backgroundColor" => "#ffffff"
                ],
                "body" => [
                    "backgroundColor" => "#eeeeee"
                ],
                "woocommerce" => [
                    "headingFontFamily" => "Arial"
                ]
            ],
            'blockDefaults' => new stdClass()
        ];

        $body_json = json_encode($body_array, JSON_UNESCAPED_SLASHES);

        $controller = $container->get(\MailPoet\Newsletter\NewsletterSaveController::class);
        $list_id = 3;

        $lists_repo = $container->get(\MailPoet\Segments\SegmentsRepository::class);
        $list = $lists_repo->findOneById($list_id);
        if (!$list) {
            file_put_contents($log_path, "❌ List ID {$list_id} not found.\n", FILE_APPEND);
            return;
        }
		
		// Clean subject
        $title_plain = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title_plain = str_replace(['’', '‘'], "'", $title_plain);
        $title_plain = str_replace(['“', '”'], '"', $title_plain);

        // Create newsletter draft
        $newsletter = $controller->save([
            'name' => 'Auto Newsletter - ' . $title_plain,
            'subject' => $title_plain,
            'type' => 'standard',
            'status' => 'draft',
            'editor' => 'drag_and_drop',
            'body' => $body_json,
            'segments' => [['id' => $list_id]],
        ]);

        if (!$newsletter || !method_exists($newsletter, 'getId')) {
            throw new Exception('Invalid newsletter object or missing getId method.');
        }

        $newsletter_id = $newsletter->getId();
        file_put_contents($log_path, "📝 Draft created (ID: {$newsletter_id})\n", FILE_APPEND);
        file_put_contents($log_path, "📌 Assigned segment ID: {$list_id}\n", FILE_APPEND);

        // Send immediately
        $controller->save([
            'id' => $newsletter_id,
            'status' => 'scheduled',
        ]);
        file_put_contents($log_path, "🚀 Newsletter status set to scheduled.\n", FILE_APPEND);


        $queue_repo = $container->get(\MailPoet\Newsletter\Sending\SendingQueuesRepository::class);
        $task_repo = $container->get(\MailPoet\Newsletter\Sending\ScheduledTasksRepository::class);

        // Extract EntityManager after $queue_repo is initialized
        $entity_manager = (new \ReflectionClass($queue_repo))->getParentClass()->getProperty('entityManager');
        $entity_manager->setAccessible(true);
        $entity_manager = $entity_manager->getValue($queue_repo);

        $now = new \DateTime('now');
        $send_sched = clone $now; // Create a separate DateTime object
        $send_sched->modify('+5 minutes');

        $queue = $newsletter->getLatestQueue();
        if (!$queue) {
            $queue = new \MailPoet\Entities\SendingQueueEntity();
            $queue->setNewsletter($newsletter);
            $queue->setCreatedAt($now);

            file_put_contents($log_path, "✅ Queue entity manually created.\n", FILE_APPEND);
        }

        $task = $queue->getTask();
        if (!$task instanceof \MailPoet\Entities\ScheduledTaskEntity) {
            $task = new \MailPoet\Entities\ScheduledTaskEntity();
            $task->setStatus(\MailPoet\Entities\ScheduledTaskEntity::STATUS_SCHEDULED);
            $task->setScheduledAt($send_sched);
            $task->setCreatedAt($now);
            $task->setType('sending');

            file_put_contents($log_path, "✅ Scheduled task manually created.\n", FILE_APPEND);
        }

        // Link them
        $task->setSendingQueue($queue);
        $queue->setTask($task); // ✅ This sets the required task_id

        // Persist both
        $entity_manager->persist($queue);
        $entity_manager->persist($task);
        $entity_manager->flush();

        // ✅ Tell MailPoet the newsletter is now sending
        $newsletter->setStatus(\MailPoet\Entities\NewsletterEntity::STATUS_SCHEDULED);
        $entity_manager->persist($newsletter);
        $entity_manager->flush();

        update_post_meta($post_id, '_gemini_newsletter_sent', true);
        file_put_contents($log_path, "=== ✅ Post marked as sent. ===\n\n", FILE_APPEND);

    } catch (Throwable $e) {
        $error = '[' . current_time('mysql') . '] ❌ MailPoet Auto-Send Error: ' . $e->getMessage();
        file_put_contents($log_path, $error . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
        error_log($error);
    }
}

add_action('gemini_run_mailpoet_newsletter_send', 'gemini_run_mailpoet_newsletter_send');