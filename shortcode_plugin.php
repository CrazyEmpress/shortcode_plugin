<?php
	/*
	Plugin Name: Shortcode плагин
	Description: Плагин для вывода постов с использованием шорткода и страницы настроек.
	Version: 1.0
	Author: Данил Коляда
	*/

	require __DIR__ . '/vendor/autoload.php';

	use Monolog\Logger;
	use Monolog\Handler\StreamHandler;

	if (!defined('ABSPATH')) {
		exit; // Exit if accessed directly.
	}

	/**
	 * Class ShortcodePlugin
	 *
	 * Основной класс плагина для управления настройками и шорткодом.
	 */
	class ShortcodePlugin {
		/**
		 * @var array Массив опций плагина.
		 */
		private $options;

		/**
		 * @var Logger Экземпляр Monolog Logger для логирования.
		 */
		private $logger;

		/**
		 * ShortcodePlugin constructor.
		 * Инициализация логгера и регистрация хуков.
		 */
		public function __construct() {
			$this->logger = new Logger('shortcode_plugin');
			$this->logger->pushHandler(new StreamHandler(__DIR__ . '/logs/plugin.log', Logger::DEBUG));

			add_action('admin_menu', [$this, 'add_admin_menu']);
			add_action('admin_init', [$this, 'register_settings']);
			add_shortcode('latest_posts', [$this, 'render_shortcode']);
		}

		/**
		 * Добавляет страницу настроек в админку WordPress.
		 */
		public function add_admin_menu() {
			add_options_page(
				'Настройки Shortcode плагина',
				'Shortcode плагин',
				'manage_options',
				'shortcode_plugin',
				[$this, 'create_admin_page']
			);
		}

		/**
		 * Регистрирует настройки плагина и добавляет секции и поля на страницу настроек.
		 */
		public function register_settings() {
			register_setting('shortcode_plugin_options', 'shortcode_plugin_options', [
				'sanitize_callback' => [$this, 'sanitize']
			]);

			add_settings_section(
				'shortcode_plugin_section',
				'Основные настройки',
				function () {
					echo 'Пока здесь только одна настройка';
				},
				'shortcode_plugin'
			);

			add_settings_field(
				'post_count',
				'Количество постов',
				[$this, 'post_count_callback'],
				'shortcode_plugin',
				'shortcode_plugin_section'
			);
		}

		/**
		 * Очистка входных данных из формы настроек.
		 *
		 * @param array $input Входные данные.
		 * @return array Санитизированные данные.
		 */
		public function sanitize($input) {
			$this->logger->debug('Проверка работы monolog | Данные на входе в sanitize:', $input);
			$new_input = [];
			if (isset($input['post_count'])) {
				$new_input['post_count'] = absint($input['post_count']);
			}
			return $new_input;
		}

		/**
		 * Колбэк функция для вывода поля количества постов.
		 */
		public function post_count_callback() {
			printf(
				'<input type="number" id="post_count" name="shortcode_plugin_options[post_count]" value="%s" />',
				isset($this->options['post_count']) ? esc_attr($this->options['post_count']) : ''
			);
		}

		/**
		 * Создание страницы настроек плагина в админке.
		 */
		public function create_admin_page() {
			$this->options = get_option('shortcode_plugin_options');
			?>
            <div class="wrap">
                <h1>Настройки плагина Shortcode</h1>
                <form method="post" action="options.php">
					<?php
						settings_fields('shortcode_plugin_options');
						do_settings_sections('shortcode_plugin');
						submit_button();
					?>
                </form>
            </div>
			<?php
		}

		/**
		 * Рендер шорткода для вывода последних постов.
		 *
		 * @return string HTML код для вывода последних постов.
		 */
		public function render_shortcode() {
			$this->logger->info('Проверка работы monolog | render_shortcode вызван');
			try {
				$options = get_option('shortcode_plugin_options');
				$post_count = isset($options['post_count']) ? $options['post_count'] : 10;

				$query = new WP_Query([
					'posts_per_page' => $post_count,
					'post_status' => 'publish'
				]);

				if ($query->have_posts()) {
					$output = '<ul>';
					while ($query->have_posts()) {
						$query->the_post();
						$output .= '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
					}
					$output .= '</ul>';
					wp_reset_postdata();
				} else {
					$output = '<p>Постов не найдено</p>';
				}

				return $output;
			} catch (Exception $e) {
				$this->logger->error('Ошибка при выводе шорткода: ' . $e->getMessage());
				return '<p>Произошла ошибка при выводе постов.</p>';
			}
		}
	}

	// Инициализация плагина
	new ShortcodePlugin();
