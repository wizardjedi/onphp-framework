<?php
/***************************************************************************
 *   Copyright (C) 2006 by Konstantin V. Arkhipov                          *
 *                                                                         *
 *   This program is free software; you can redistribute it and/or modify  *
 *   it under the terms of the GNU General Public License as published by  *
 *   the Free Software Foundation; either version 2 of the License, or     *
 *   (at your option) any later version.                                   *
 *                                                                         *
 ***************************************************************************/
/* $Id$ */

	/**
	 * @ingroup Patterns
	**/
	abstract class BasePattern extends Singleton implements GenerationPattern
	{
		public function daoExists()
		{
			return false;
		}
		
		public static function dumpFile($path, $content)
		{
			$content = trim($content);
			
			if (is_readable($path)) {
				$pattern =
					array(
						'@\/\*(.*)\*\/@sU',
						'@[\r\n]@sU'
					);
				
				$old = preg_replace($pattern, null, file_get_contents($path));
				$new = preg_replace($pattern, null, $content);
			} else {
				$old = 1; $new = 2;
			}
			
			$out = MetaConfiguration::out();
			$className = basename($path, EXT_CLASS);
			
			if ($old !== $new) {
				$out->
					warning("\t\t".$className.' ');
				
				$fp = fopen($path, 'wb');
				fwrite($fp, $content);
				fclose($fp);
				
				$out->
					log('(')->
					remark(
						str_replace(getcwd().DIRECTORY_SEPARATOR, null, $path)
					)->
					logLine(')');
			} else {
				$out->
					infoLine("\t\t".$className.' ', true);
			}
		}
		
		protected function fullBuild(MetaClass $class)
		{
			$this->dumpFile(
				ONPHP_META_AUTO_PROTO_DIR.'Proto'.$class->getName().EXT_CLASS,
				Format::indentize(ProtoClassBuilder::build($class))
			);
			
			$this->dumpFile(
				ONPHP_META_AUTO_BUSINESS_DIR.'Auto'.$class->getName().EXT_CLASS,
				Format::indentize(AutoClassBuilder::build($class))
			);
			
			$this->dumpFile(
				ONPHP_META_AUTO_DAO_DIR.'Auto'.$class->getName().'DAO'.EXT_CLASS,
				Format::indentize(AutoDaoBuilder::build($class))
			);
			
			$userFile = ONPHP_META_BUSINESS_DIR.$class->getName().EXT_CLASS;
			
			if (!file_exists($userFile))
				$this->dumpFile(
					$userFile,
					Format::indentize(BusinessClassBuilder::build($class))
				);
			
			$userFile = ONPHP_META_DAO_DIR.$class->getName().'DAO'.EXT_CLASS;
			
			if (!file_exists($userFile))
				$this->dumpFile(
					$userFile,
					Format::indentize(DaoBuilder::build($class))
				);
			
			// supplementary classes check
			foreach ($class->getProperties() as $property) {
				if (
					$property->getRelation()
					&& (
						$property->getRelation()->getId()
						!= MetaRelation::ONE_TO_ONE
					)
				) {
					$userFile =
						ONPHP_META_DAO_DIR
						.$class->getName().ucfirst($property->getName())
						.'DAO'
						.EXT_CLASS;
					
					if (!file_exists($userFile)) {
						$this->dumpFile(
							$userFile,
							Format::indentize(
								ContainerClassBuilder::buildContainer(
									$class,
									$property
								)
							)
						);
					}
					
					// check for old-style naming
					$oldStlye = 
						ONPHP_META_DAO_DIR
						.$class->getName()
						.'To'
						.$property->getType()->getClass()
						.'DAO'
						.EXT_CLASS;
					
					if (is_readable($oldStlye)) {
						MetaConfiguration::out()->
							newLine()->
							error(
								'remove manually: '.$oldStlye
							);
					}
				}
			}
		}
	}
?>