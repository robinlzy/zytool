<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */

namespace Ziyanco\Zytool\Command;


use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use function Ziyanco\Library\Command\snakeToCamel;

#[Command]
class ModelCommand extends HyperfCommand
{
    public function __construct(protected ContainerInterface $container)
    {
        parent::__construct('model:public');
    }

    public function configure()
    {
        parent::configure();
        $this->addArgument('name', InputArgument::REQUIRED);
        $this->setDescription('Hyperf Model Public');
    }

    public function handle()
    {
        $dbPrefix = \Hyperf\Support\env('DB_PREFIX');
        $argument = $this->input->getArgument('name');
        $classModel = snakeToCamel($argument);
        $tableName=$dbPrefix . $argument;
        $modelStub = file_get_contents(__DIR__ . '/../Generator/stubs/model.stub');
        $tableModelStub = file_get_contents(__DIR__ . '/../Generator/stubs/tableModel.stub');
        $columns = \Hyperf\DbConnection\Db::select('SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = \''. $tableName.'\';');
        $fillableString='';
        $propertyListString='';
        $castsAbleString='';
        foreach ($columns as $item){
            if($item->COLUMN_KEY!='PRI'){
                $fillableString=$fillableString.$this->getFillableString($item);
            }
            $propertyListString=$propertyListString.$this->getPropertyListString($item);
            if(!empty($this->getAastsAbleType($item))){
                $castsAbleString=$castsAbleString.$this->getAastsAbleType($item);
            }
        }
        $fillAbleReplace=empty($fillableString)?'':substr($fillableString,0,strlen($fillableString)-1);
        $castsAbleString=empty($castsAbleString)?'':substr($castsAbleString,0,strlen($castsAbleString)-1);
        //替换生成完整code代码
        $modelStub=str_replace([
            '{CLASS_NAME}',
            '{TABLE_NAME}',
            '{PROPERTY_LIST}',
            '{FILL_ABLE}',
            '{CASTS_ABLE}'
        ],[
            $classModel,
            $argument,
            $propertyListString,
            $fillAbleReplace,
            $castsAbleString
        ],$modelStub);

        $tableModelStub=str_replace('{CLASS_NAME}',$classModel,$tableModelStub);
        file_put_contents(BASE_PATH.'/app/Model/Table/'.$classModel.'.php',$modelStub,FILE_APPEND);
        file_put_contents(BASE_PATH.'/app/Model/'.$classModel.'Model.php',$tableModelStub,FILE_APPEND);
        $this->line('----成功:生成Model完成-----');
    }


    /**
     * 生成属性
     * @param $item
     * @return string
     */
    public function getPropertyListString($item){
        if(in_array($item->DATA_TYPE,['int','tinyint','bigint'])){
            return '* @property int $'.$item->COLUMN_NAME.' '.$item->COLUMN_COMMENT.PHP_EOL;
        }else if(in_array($item->DATA_TYPE,['float','decimal'])){
            return '* @property float $'.$item->COLUMN_NAME.' '.$item->COLUMN_COMMENT.PHP_EOL;
        }else {
            return '* @property string $'.$item->COLUMN_NAME.' '.$item->COLUMN_COMMENT.PHP_EOL;
        }
    }

    /**
     * 生成casts
     * @param $item
     * @return string
     */
    public function getAastsAbleType($item){
        if(in_array($item->DATA_TYPE,['int','tinyint','bigint'])){
            return '\''.$item->COLUMN_NAME.'\''.'=>\'integer\',';
        }else if(in_array($item->DATA_TYPE,['float'])){
            return '\''.$item->COLUMN_NAME.'\''.'=>\'float\',';
        }else if(in_array($item->DATA_TYPE,['decimal'])){
            return '\''.$item->COLUMN_NAME.'\''.'=>\'decimal:2\',';
        }else {
            return '';
        }
    }

    public function getFillableString($item){
         return '\''.$item->COLUMN_NAME.'\',';
    }


}
