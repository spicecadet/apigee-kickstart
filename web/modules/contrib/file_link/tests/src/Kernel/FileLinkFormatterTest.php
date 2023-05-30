<?php

namespace Drupal\Tests\file_link\Kernel;

use Drupal\Core\Site\Settings;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the file link formatters.
 *
 * @group file_link
 */
class FileLinkFormatterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'file_link',
    'file_link_test',
    'entity_test',
    'link',
    'field',
    'user',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installConfig(['file_link_test']);

    $settings = Settings::getAll();
    // Set up the fixtures.
    $settings['file_link_test_middleware'] = [
      'http://file_link.drupal/fancy-file-1.txt' => [
        'status' => 200,
        'headers' => ['Content-Type' => 'text/plain', 'Content-Length' => 27],
      ],
      'http://file_link.drupal/fancy-file-2.md' => [
        'status' => 200,
        'headers' => [
          'Content-Type' => 'text/markdown',
          'Content-Length' => 42784327,
        ],
      ],
      'http://file_link.drupal/broken-file-response.txt' => [
        'status' => 200,
        'headers' => [],
      ],
      'http://file_link.drupal/file-no-type.md' => [
        'status' => 200,
        'headers' => ['Content-Length' => 476],
      ],
    ];

    new Settings($settings);

    $this->entity = EntityTest::create([
      'name' => $this->randomMachineName(),
      'type' => 'article',
      'multivalue_url' => [
        [
          'uri' => 'http://file_link.drupal/fancy-file-1.txt',
        ],
        [
          'uri' => 'http://file_link.drupal/fancy-file-2.md',
          'title' => 'A very long & strange example title that could break the nice layout of the site.',
        ],
        [
          'uri' => 'http://file_link.drupal/broken-file-response.txt',
          'title' => 'A file with no type nor size.',
        ],
        [
          'uri' => 'http://file_link.drupal/file-no-type.md',
          'title' => 'A file with no type.',
        ],
      ],
    ]);
    $this->entity->save();
  }

  /**
   * Tests the file_link formatter.
   */
  public function testFileLinkFormatter(): void {
    $display_options = [
      'type' => 'file_link',
      'label' => 'hidden',
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertCount(4, $items);

    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drupal/fancy-file-1.txt</a> (text/plain, 27 bytes)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-2.md">A very long &amp; strange example title that could break the nice layout of the sit…</a> (text/markdown, 40.8 MB)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/broken-file-response.txt">A file with no type nor size.</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/file-no-type.md">A file with no type.</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));

    $display_options['settings'] = [
      'format_size' => FALSE,
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drupal/fancy-file-1.txt</a> (text/plain, 27)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-2.md">A very long &amp; strange example title that could break the nice layout of the sit…</a> (text/markdown, 42784327)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/broken-file-response.txt">A file with no type nor size.</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/file-no-type.md">A file with no type.</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));

    $display_options['settings'] = [
      'trim_length' => 22,
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drup…</a> (text/plain, 27 bytes)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-2.md">A very long &amp; strange…</a> (text/markdown, 40.8 MB)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/broken-file-response.txt">A file with no type n…</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/file-no-type.md">A file with no type.</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));

    $display_options['settings'] = [
      'trim_length' => NULL,
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drupal/fancy-file-1.txt</a> (text/plain, 27 bytes)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-2.md">A very long &amp; strange example title that could break the nice layout of the site.</a> (text/markdown, 40.8 MB)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/broken-file-response.txt">A file with no type nor size.</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div><a href="http://file_link.drupal/file-no-type.md">A file with no type.</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));
  }

  /**
   * Tests the file_link_separate formatter.
   */
  public function testFileLinkSeparateFormatter(): void {
    $display_options = [
      'type' => 'file_link_separate',
      'label' => 'hidden',
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertCount(4, $items);

    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drupal/fancy-file-1.txt</a> (text/plain, 27 bytes)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div>A very long &amp; strange example title that could break the nice layout of the sit… <a href="http://file_link.drupal/fancy-file-2.md">http://file_link.drupal/fancy-file-2.md</a> (text/markdown, 40.8 MB)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div>A file with no type nor size. <a href="http://file_link.drupal/broken-file-response.txt">http://file_link.drupal/broken-file-response.txt</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div>A file with no type. <a href="http://file_link.drupal/file-no-type.md">http://file_link.drupal/file-no-type.md</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));

    $display_options['settings'] = [
      'format_size' => FALSE,
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drupal/fancy-file-1.txt</a> (text/plain, 27)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div>A very long &amp; strange example title that could break the nice layout of the sit… <a href="http://file_link.drupal/fancy-file-2.md">http://file_link.drupal/fancy-file-2.md</a> (text/markdown, 42784327)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div>A file with no type nor size. <a href="http://file_link.drupal/broken-file-response.txt">http://file_link.drupal/broken-file-response.txt</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div>A file with no type. <a href="http://file_link.drupal/file-no-type.md">http://file_link.drupal/file-no-type.md</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));

    $display_options['settings'] = [
      'trim_length' => 22,
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drup…</a> (text/plain, 27 bytes)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div>A very long &amp; strange… <a href="http://file_link.drupal/fancy-file-2.md">http://file_link.drup…</a> (text/markdown, 40.8 MB)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div>A file with no type n… <a href="http://file_link.drupal/broken-file-response.txt">http://file_link.drup…</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div>A file with no type. <a href="http://file_link.drupal/file-no-type.md">http://file_link.drup…</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));

    $display_options['settings'] = [
      'trim_length' => NULL,
    ];
    $this->renderEntityField($display_options);
    $items = $this->cssSelect('main > div > div');
    $this->assertEquals('<div><a href="http://file_link.drupal/fancy-file-1.txt">http://file_link.drupal/fancy-file-1.txt</a> (text/plain, 27 bytes)</div>', $this->normaliseSpaces((string) $items[0]->asXML()));
    $this->assertEquals('<div>A very long &amp; strange example title that could break the nice layout of the site. <a href="http://file_link.drupal/fancy-file-2.md">http://file_link.drupal/fancy-file-2.md</a> (text/markdown, 40.8 MB)</div>', $this->normaliseSpaces((string) $items[1]->asXML()));
    $this->assertEquals('<div>A file with no type nor size. <a href="http://file_link.drupal/broken-file-response.txt">http://file_link.drupal/broken-file-response.txt</a></div>', $this->normaliseSpaces((string) $items[2]->asXML()));
    $this->assertEquals('<div>A file with no type. <a href="http://file_link.drupal/file-no-type.md">http://file_link.drupal/file-no-type.md</a></div>', $this->normaliseSpaces((string) $items[3]->asXML()));
  }

  /**
   * Renders the test entity field with the given display options.
   *
   * @param array $display_options
   *   The options for the file link field.
   */
  protected function renderEntityField(array $display_options): void {
    $build = $this->entity->get('multivalue_url')->view($display_options);

    $this->render($build);
  }

  /**
   * Convert whitespace characters into spaces.
   *
   * Multiple consecutive whitespace characters are converted into a single one.
   *
   * @param string $html
   *   The HTMl to process.
   *
   * @return string
   *   The normalised HTML.
   */
  protected function normaliseSpaces(string $html): string {
    return preg_replace('/\s+/u', ' ', $html);
  }

}
