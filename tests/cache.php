<?php

namespace KrtekBase;

/**
 * Krtek_Cache class tests
 * @group KrtekCache
 */
class Test_Cache extends \Fuel\Core\TestCase {
	/**
	 * @test
	 * @dataProvider getBasicData
	 */
	public function test_save($data) {
		Krtek_Cache::save($data->id, $data);

		$this->assertTrue(Krtek_Cache::has($data->id));
		$this->assertFalse(Krtek_Cache::has(-1));
		$this->assertEquals($data, Krtek_Cache::get($data->id));
	}

	/**
	 * @test
	 */
	public function test_save2() {
		foreach($this->getBasicData() as $data) {
			$data = $data[0];
			Krtek_Cache::save($data->id, $data);
		}

		foreach($this->getBasicData() as $data) {
			$data = $data[0];
			$this->assertTrue(Krtek_Cache::has($data->id));
			$this->assertEquals($data, Krtek_Cache::get($data->id));
		}
	}

	/**
	 * @test
	 * @dataProvider getBasicData
	 */
	public function test_save_column($data) {
		Krtek_Cache::save($data->value, $data, 'value');

		$this->assertTrue(Krtek_Cache::has($data->value, 'value'));
		$this->assertFalse(Krtek_Cache::has(-1, 'value'));
		$this->assertEquals($data, Krtek_Cache::get($data->value, null, 'value'));

		$this->assertTrue(Krtek_Cache::has($data->id));
		$this->assertEquals($data, Krtek_Cache::get($data->id));
	}

	/**
	 * @test
	 */
	public function test_overwrite() {
		foreach($this->getBasicData() as $data) {
			$data = $data[0];
			Krtek_Cache::save($data->id, $data);
		}

		$this->assertTrue(Krtek_Cache::has(1));
		$this->assertTrue(Krtek_Cache::has(2));
		$this->assertTrue(Krtek_Cache::has(3));

		Krtek_Cache::save(1, 'plop');
		Krtek_Cache::save(1, 'plap');
		Krtek_Cache::save(1, 'plip');
		Krtek_Cache::save(2, 'foo');
		Krtek_Cache::save(2, 'bar');
		Krtek_Cache::save(3, 'mickey');

		$this->assertEquals('plip', Krtek_Cache::get(1));
		$this->assertEquals('bar', Krtek_Cache::get(2));
		$this->assertEquals('mickey', Krtek_Cache::get(3));

		foreach($this->getBasicData() as $data) {
			$data = $data[0];
			Krtek_Cache::save($data->value, $data, 'value');
		}

		foreach($this->getBasicData() as $data) {
			$data = $data[0];
			$this->assertEquals($data, Krtek_Cache::get($data->id));
		}
	}

	/**
	 * @test
	 * @dataProvider getBasicData
	 * @expectedException KrtekBase\Cache_WrongType_Exception
	 */
	public function test_class($data) {
		Krtek_Cache::save($data->id, $data);
		Krtek_Cache::get($data->id, 'Toto');
	}

	/**
	 * @test
	 * @dataProvider getBasicData
	 * @expectedException KrtekBase\Cache_NotFound_Exception
	 */
	public function test_get($data) {
		Krtek_Cache::save($data->id, $data);
		Krtek_Cache::get(- 1);
	}

	/**
	 * @test
	 * @dataProvider getBasicData
	 * @expectedException KrtekBase\Cache_NoMapping_Exception
	 */
	public function test_get_column($data) {
		Krtek_Cache::save($data->id, $data);
		Krtek_Cache::get(-1, null, 'value');
	}

	/**
	 * @test
	 * @dataProvider getBasicData
	 */
	public function test_clear($data) {
		Krtek_Cache::save($data->id, $data);
		Krtek_Cache::clear($data->id);
		$this->assertFalse(Krtek_Cache::has($data->id));

		Krtek_Cache::save($data->value, $data, 'value');
		Krtek_Cache::clear($data->value, 'value');
		$this->assertFalse(Krtek_Cache::has($data->value, 'value'));
		$this->assertFalse(Krtek_Cache::has($data->id));
	}

	/**
	 * @test
	 */
	public function test_clear_all() {
		foreach($this->getBasicData() as $data) {
			$data = $data[0];
			Krtek_Cache::save($data->id, $data);
		}
		Krtek_Cache::clear_all();

		foreach($this->getBasicData() as $data) {
			$data = $data[0];
			$this->assertFalse(Krtek_Cache::has($data->id));
		}
	}

	/**
	 * @test
	 */
	public function test_results_cache_save() {
		Krtek_Cache::results_cache_save('table', 'column', array(1 => 'toto'));
		Krtek_Cache::results_cache_save('table', 'column2', array(1 => 'titi'));
		Krtek_Cache::results_cache_save('table2', 'column2', array(42 => 'tata'));

		$this->assertEquals('toto', Krtek_Cache::results_cache_get('table', 'column', 1));
		$this->assertEquals('titi', Krtek_Cache::results_cache_get('table', 'column2', 1));
		$this->assertNull(Krtek_Cache::results_cache_get('table', 'column', 2));

		$this->assertEquals(Krtek_Cache::results_cache_get('table2', 'column2', 42), 'tata');

		$this->assertFalse(Krtek_Cache::results_cache_get('table2', 'column3', 42));
	}

	public function getBasicData() {
		return array(
			array((object) array('id' => 1, 'value' => 'toto')),
			array((object) array('id' => 2, 'value' => 'titi')),
			array((object)array('id' => 3, 'value' => 'tata')),
		);
	}
}