<?php
	/* $Id$ */
	
	class DAOTest extends TestTables
	{
		public function testSchema()
		{
			return $this->create()->drop();
		}
		
		public function testData()
		{
			$this->create();
			
			foreach (DBTestPool::me()->getPool() as $connector => $db) {
				DBPool::me()->setDefault($db);
				$this->fill();
				
				$this->getSome(); // 41!
				Cache::me()->clean();
				$this->getSome();
				
				$this->nonIntegerIdentifier();
				$this->persistenceInIdentityMap();
				
				$this->racySave();
				$this->lazyTest();
			}
			
			$this->drop();
		}
		
		public function testGetByEmptyId()
		{
			$this->create();
			
			$this->getByEmptyIdTest(0);
			$this->getByEmptyIdTest(null);
			$this->getByEmptyIdTest('');
			$this->getByEmptyIdTest('0');
			$this->getByEmptyIdTest(false);
			
			$this->drop();
		}
		
		public function fill($assertions = true)
		{
			$moscow =
				TestCity::create()->
				setName('Moscow');
			
			$piter =
				TestCity::create()->
				setName('Saint-Peterburg');
			
			$mysqler =
				TestUser::create()->
				setCity($moscow)->
				setCredentials(
					Credentials::create()->
					setNickname('mysqler')->
					setPassword(sha1('mysqler'))
				)->
				setLastLogin(
					Timestamp::create(time())
				)->
				setRegistered(
					Timestamp::create(time())->modify('-1 day')
				);
			
			$postgreser = clone $mysqler;
			
			$postgreser->
				setCredentials(
					Credentials::create()->
					setNickName('postgreser')->
					setPassword(sha1('postgreser'))
				)->
				setCity($piter);
			
			$piter = TestCity::dao()->add($piter);
			$moscow = TestCity::dao()->add($moscow);
			
			if ($assertions) {
				$this->assertEqual($piter->getId(), 1);
				$this->assertEqual($moscow->getId(), 2);
			}
			
			$postgreser = TestUser::dao()->add($postgreser);
			$mysqler = TestUser::dao()->add($mysqler);
			
			if ($assertions) {
				$this->assertEqual($postgreser->getId(), 1);
				$this->assertEqual($mysqler->getId(), 2);
			}
			
			if ($assertions) {
				// put them in cache now
				TestUser::dao()->dropIdentityMap();
				
				TestUser::dao()->getById(1);
				TestUser::dao()->getById(2);
				
				$this->getListByIdsTest();
				
				Cache::me()->clean();
				
				$this->assertTrue(
					($postgreser == TestUser::dao()->getById(1))
				);
				
				$this->assertTrue(
					($mysqler == TestUser::dao()->getById(2))
				);
			}
			
			$firstClone = clone $postgreser;
			$secondClone = clone $mysqler;
			
			TestUser::dao()->dropById($postgreser->getId());
			TestUser::dao()->dropByIds(array($mysqler->getId()));
			
			if ($assertions) {
				try {
					TestUser::dao()->getById(1);
					$this->fail();
				} catch (ObjectNotFoundException $e) {
					$this->pass();
				}
				
				$result =
					Criteria::create(TestUser::dao())->
					add(Expression::eq(1, 2))->
					getResult();
				
				$this->assertEqual($result->getCount(), 0);
				$this->assertEqual($result->getList(), array());
			}
			
			TestUser::dao()->import($firstClone);
			TestUser::dao()->import($secondClone);
			
			if ($assertions) {
				// cache multi-get
				$this->getListByIdsTest();
				$this->getListByIdsTest();
			}
		}
		
		public function nonIntegerIdentifier()
		{
			$id = 'non-integer one';
			
			$bin =
				TestBinaryStuff::create()->
				setId($id)->
				setData("\0!bbq!\0");
			
			try {
				TestBinaryStuff::dao()->import($bin);
			} catch (DatabaseException $e) {
				die();
			}
			
			Cache::me()->clean();
			
			$prm = Primitive::identifier('id')->of('TestBinaryStuff');
			
			$this->assertTrue($prm->import(array('id' => $id)));
			$this->assertIdentical($prm->getValue()->getId(), $id);
			
			$this->assertEqual(TestBinaryStuff::dao()->getById($id), $bin);
			$this->assertEqual(TestBinaryStuff::dao()->dropById($id), 1);
		}
		
		protected function getSome()
		{
			for ($i = 1; $i < 3; ++$i) {
				$this->assertTrue(
					TestUser::dao()->getByLogic(
						Expression::eq('city_id', $i)
					)
					== TestUser::dao()->getById($i)
				);
			}
			
			$this->assertEqual(
				count(TestUser::dao()->getPlainList()),
				count(TestCity::dao()->getPlainList())
			);
		}
		
		private function persistenceInIdentityMap()
		{
			$user1 = TestUser::dao()->getById(1);
			$user2 = TestUser::dao()->getById(1);
			
			$user3 = TestUser::dao()->getByLogic(Expression::eq('id', 1));
			$user4 = TestUser::dao()->getByLogic(Expression::eq('id', 1));
			
			$this->assertIdentical($user1, $user2);
			$this->assertIdentical($user3, $user4);
			$this->assertIdentical($user1, $user4);
			
			$users = TestUser::dao()->getListByIds(array(1, 2));
			
			$this->assertIdentical($users[0], $user1);
		}
		
		private function racySave()
		{
			$lost =
				TestCity::create()->
				setId(424242)->
				setName('inexistant city');
			
			try {
				TestCity::dao()->save($lost);
				
				$this->fail();
			} catch (WrongStateException $e) {
				$this->pass();
			}
		}
		
		private function getListByIdsTest()
		{
			TestUser::dao()->dropIdentityMap();
			
			$list = TestUser::dao()->getListByIds(array(1, 3, 2, 1, 1, 1));
			
			// dupes will not be ignored in >=1.0
			$this->assertEqual(count($list), 2);
			
			// since we can't expect any order here
			// order will be respected in >=1.0
			if ($list[0]->getId() > $list[1]->getId()) {
				Range::swap($list[0], $list[1]);
			}
			
			$this->assertEqual($list[0]->getId(), 1);
			$this->assertEqual($list[1]->getId(), 2);
			
			$this->assertEqual(
				array(),
				TestUser::dao()->getListByIds(array(42, 42, 1738))
			);
		}
		
		private function lazyTest()
		{
			$city = TestCity::dao()->getById(1);
			
			$object = TestLazy::dao()->add(
				TestLazy::create()->
					setCity($city)->
					setCityOptional($city)->
					setEnum(
						new ImageType(ImageType::getAnyId())
					)
			);
			
			Cache::me()->clean();
			
			$form = TestLazy::proto()->makeForm();
			$form->import(
				array('id' => $object->getId())
			);
			
			$this->assertNotNull($form->getValue('id'));
			
			FormUtils::object2form($object, $form);
			
			foreach ($object->proto()->getPropertyList() as $name => $property) {
				if (
					$property->getRelationId() == MetaRelation::ONE_TO_ONE
					&& $property->getFetchStrategyId() == FetchStrategy::LAZY
				) {
					$this->assertEqual(
						$object->{'get'.ucfirst($name)}(),
						$form->getValue($name)
					);
				}
			}
		}
		
		private function getByEmptyIdTest($id)
		{
			try {
				TestUser::dao()->getById($id);
				$this->fail();
			} catch (WrongArgumentException $e) {
				// pass
			}
		}
	}
?>