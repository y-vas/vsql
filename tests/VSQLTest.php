<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use VSQL\VSQL\VSQL;

// // DISABLE INSPET TO prevent from swhowing the inspect mode
$_ENV['VSQL_INSPECT'] = false;
$_ENV[  'DB_HOST'  ] = '172.29.0.2';
$_ENV['DB_USERNAME'] = 'root';
$_ENV['DB_PASSWORD'] = 'root';
$_ENV['DB_DATABASE'] = 'dbtest';

final class VSQL_TEST extends TestCase {
    public $vsql = null;
    public $select_test_query = "SELECT
     *
    FROM Products p {
      INNER JOIN Users u on p.user_id = u.id
      JOIN_USERS;
    }
    WHERE TRUE
    { AND p.name like '%:like%' }
    { LIMIT +i:limit }
    ";

    public $insert_test_query = "INSERT
        INTO Products ( id, name, type, num, user_id, cost )
        VALUES      (
          null,
          s:name,
          +i:type,
          +i:num ? 0; ,
          +i:user_id,
          +i:cost
      )
    ";

    public $update_test_query = "

    ";

    protected function setUp(){
      try {
        $this->vsql = new VSQL();
      } catch (\Exception $e) {
        $this->fail('Could not connect mysqli with VSQL!');
      }

      $this->assertNotNull(
          $this->vsql,
          "VSQL is not null"
      );
    }

    public function testSimpleSELECT(): void {
        $query = "SELECT * FROM Products LIMIT 2";
        $retun_query = $this->vsql->query( $query, [] );

        $this->assertEquals(
            $retun_query,
            $query
        );

        $this->assertInstanceOf(
            'stdClass',
            $this->vsql->run(),
        );
    }

    public function testInsert(): void {
        $retun_query = $this->vsql->query(
            $this->insert_test_query, [
            'name' =>'test',
            'type' => '5',
            'num'  => 'this_should_transfom_into_0',
            'user_id' => '1',
            'cost' => '-32'
        ]);

        $insert_id = $this->vsql->run()->insert_id;

        $this->assertIsInt( $insert_id );

        // now we chech if the values are correctly inserted
        // we select the last inserted row
        $this->vsql->query( "SELECT
            * FROM Products
            WHERE name = 'test' order by id desc
            LIMIT 1
        ",[] );

        $data = $this->vsql->get();

        $this->assertEquals( $data->name, 'test' );
        $this->assertEquals( $data->type, 5 );
        $this->assertEquals( $data->num , 0 );
        $this->assertEquals( $data->user_id, 1 );
        $this->assertEquals( $data->cost, 32 );
    }

    public function testComplexSelect(): void {
        $retun_query = $this->vsql->query(
            $this->select_test_query, [
            'limit' => 2,
        ]);

        $data = (array) $this->vsql->get( true );

        $this->assertEquals( count($data), 2 );
        $this->assertEquals( count((array)$data[0]), 6 );

        $retun_query = $this->vsql->query(
            $this->select_test_query, [
            'JOIN_USERS' => 2,
        ]);

        $data = (array) $this->vsql->get( true );
        $this->assertEquals( count((array)$data[0]), 9 );
    }

}
