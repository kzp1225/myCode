<?php
namespace app\index\controller;
use think\Db;

class DailyCurve
{
    public function Anaylsis()
    {
        
        //从postBody里得到原始数据
        $rawData = input('param.');
        // return json($rawData);
        
		//原始数据记录
        $inputData=$rawData['data'];
        
        //定义公用参数
        $devId=input('devId');
		
		//开始时间，结束时间
        $startTime = input('startTime',$rawData['startTime']);
        $endTime = input('endTime',($rawData['startTime']+86400));
        // $startTime = $startTime." 00:00:00";
        // $endTime = $endTime." 23:59:59";
        // $startTime = strtotime($startTime);
        // $endTime = strtotime($endTime);
 
		//默认标准值
		$stdValueDefault = 150;
		
		//标准值
        $stdValue=input('stdValue',$stdValueDefault);
		
		//标准值误差范围
        $stdValueError=input('stdValueError',$stdValueDefault*0.005/150);
		
		//超限阈值
        $overRunUp1=input('overRunUp1',  $stdValueDefault*157.5/150 );
        $overRunLow1=input('overRunLow1',$stdValueDefault*142.5/150 );
        $overRunUp2=input('overRunUp2',  $stdValueDefault*154.5/150 );
        $overRunLow2=input('overRunLow2',$stdValueDefault*145.5/150 );
        $overRunUp3=input('overRunUp3',  $stdValueDefault*153  /150 );
        $overRunLow3=input('overRunLow3',$stdValueDefault*147  /150 );
        $overRunTime=input('overRunTime',$stdValueDefault*3    /150 );

		//突变阈值
        $sharpUp1=input('sharpUp1',   $stdValueDefault*157.5/150);
        $sharpLow1=input('sharpLow1', $stdValueDefault*142.5/150);
        $sharpUp2=input('sharpUp2',   $stdValueDefault*154.5/150);
        $sharpLow2=input('sharpLow2', $stdValueDefault*145.5/150);
        $sharpUp3=input('sharpUp3',   $stdValueDefault*153.0/150);
        $sharpLow3=input('sharpLow3', $stdValueDefault*147.0/150);
        
		//趋势角度，tan后的数值代表角度单位是°，如直角为90°
        $trendUp1=input('trendUp1',   tan(17.2 * pi()/180));
        $trendLow1=input('trendLow1',-tan(17.2 * pi()/180 ));
        $trendUp2=input('trendUp2',   tan(14.3 * pi()/180));
        $trendLow2=input('trendLow2',-tan(14.3 * pi()/180));
        $trendUp3=input('trendUp3',   tan(11.5 * pi()/180 ));
        $trendLow3=input('trendLow3',-tan(11.5 * pi()/180 ));
        $trendTime=input('trendTime',10);
		
		//斜率允许的误差
        $graError=input('graError',tan(11.5 * pi()/180 ));
		
		//先做数据预处理
		
		//最多允许连续多少个空值
		$diffCnt=3;
		for($i=0;$i<count($inputData);$i++)
		{
			$val = $inputData[$i];
			
			//向后找最多diffCnt个数据
			if(is_null($val))
			{
				$isFind = false;
				
				//存放找到的第一个非空值 
				$notNullValue = 0;
				$notNullIndex = $i;
				
				//查找第一个非空值 
				for($j=$i+1;$j<count($inputData);$j++)
				{
					$notNullIndex = $j;
					$notNullValue = $inputData[$j];
					if(!is_null($inputData[$j]))
					{
						if($j<=$i+$diffCnt)
						{
							$isFind = true;
						}
						break;
					}
				}
				
				if(!$isFind)
				{
					//在diffCnt秒内没找到有效值
					$i = $notNullIndex+1;
					continue;
				}
				else
				{
					//在diffCnt秒内找到
					if($i-1<0)
					{
						$i = $notNullIndex+1;
						continue;
					}
					$va = $inputData[$i-1];
					
					if(is_null($va))
					{
						$i = $notNullIndex+1;
						continue;
					}
					
					//取上一个值，左边为a点，右边为b点，中间要求的为x点
					$ta = $i-1;
					$tb = $notNullIndex;
					$vb = $notNullValue;
					
					//补值
					for($k=$i;$k<$tb;$k++)
					{
						$tx = $k;
						//(tx-ta)/(tb-ta)=(vx-va)/(vb-va)
						$vx = ($tx-$ta)/($tb-$ta)*($vb-$va)+$va;
						$inputData[$tx] = $vx;
					}
					
					$i = $notNullIndex+1;
					continue;
				}			
			}
		}
        
		//每秒与下一秒数据的斜率缓存
        $graPerSec=array();
        for($i=0;$i<count($inputData)-1;$i++)
        {
			$graPerSec[] = $inputData[($i+1)]-$inputData[$i];   	
        }		
		//最后一点的斜率
		$graPerSec[]=0;
		
        // dump($graPerSec);

        // 用于存放数组
        $data=array();
		
		//除去null之外的异常值
        $arrOutlier=array();
		
		//null值数组
        $null=array();



        // 找出所有异常数据
        for($i=0;$i<count($inputData);$i++)
        {
			$item=array(
				'value'=>$inputData[$i],
				'tm'=>$i
			);
			
            if(is_null($item['value']))
            {
				//空值
                $null[]=$item;
            }
            if(
				!is_null($item['value'])
				&&
				(
					$item['value']<($stdValue*(1- $stdValueError))
					||
					$item['value']>($stdValue*(1+ $stdValueError))
                )
            )
            {
                //除去null之外的异常值
                $arrOutlier[]=$item;
            }
            
        }
        // return json($arr);
        // if(count($null)==0){
        //     return json(['msg'=>'无未知情况',"code"=>200]);
        // }

        $nulls = array();
        for($i=0;$i<count($null);$i++)
        {
            $nulls[$i] = array();
            $nulls[$i][] = $null[$i];
            for($j=$i+1;$j<count($null);$j++)
            {
                if($null[$j]['tm']-$null[$i]['tm']==($j-$i))
                    {
                        array_push($nulls[$i],$null[$j]);
                    }
            }
            // if(count($nulls[$i])==1){$nulls[$i]=null;}
            //将满足条件的值追加给$arrs后将剩余的$arr全部设为null
            for($n=0;$n<count($nulls[$i]);$n++)
            {
                $null[($i+$n)] = null;
            }
            //在$arrs中搜索键值 "[null]"，并返回它的键名给$key
            $keyNull = array_search([null], $nulls);
            if ($keyNull !== false)
            {
                //在$arrs中从$key移除一个元素
                array_splice($nulls, $keyNull, 1);
            }
        }  
        $keyNull = array_search([null], $nulls);
        if ($keyNull !== false)
        {
            //在$arrs中从$key一处一个元素
            array_splice($nulls, $keyNull, 1);
        }
        // return json($nulls);

        if(count($arrOutlier)==0){
            return json(['msg'=>'无异常情况',"code"=>200,"data"=>[]]);
        }

        // 将时间连续的数据切分重组
        $arrs = array();
        for($i=0;$i<count($arrOutlier);$i++)
        {
            $arrs[$i]=array();
            $arrs[$i][] = $arrOutlier[$i];
            for($j=$i+1;$j<count($arrOutlier);$j++)
            {
                if($arrOutlier[$j]['tm']-$arrOutlier[$i]['tm']==($j-$i))
                    {
                        array_push($arrs[$i],$arrOutlier[$j]);
                    }
            }
            // if(count($arrs[$i])==1){$arrs[$i]=null;}
            //将满足条件的值追加给$arrs后将剩余的$arr全部设为null
            for($n=0;$n<count($arrs[$i]);$n++)
            {
                $arrOutlier[($i+$n)] = null;
            }
            //在$arrs中搜索键值 "[null]"，并返回它的键名给$key
            $key = array_search([null], $arrs);
            if ($key !== false)
            {
                //在$arrs中从$key一处一个元素
                array_splice($arrs, $key, 1);
            }
        }  
        $key = array_search([null], $arrs);
        if ($key !== false)
        {
            array_splice($arrs, $key, 1);
        }
        // return json($arrs);
    
        // 判断异常类型和等级
        $unualTime=array();
        for($u=0;$u<count($arrs);$u++)
        {
            $unualTime[]=count($arrs[$u]);
        }
        // return json($unualTime);
        $unknownTime=array();
        for($u=0;$u<count($nulls);$u++)
        {
            $unknownTime[]=count($nulls[$u]);
        }

        $res=array();
        $result=array();
        $arr2 = array();
        
        for($t=0;$t<count($unualTime);$t++)
        {
            for($y=0;$y<count($arrs[$t]);$y++)
            {
                if($unualTime[$t]<=$overRunTime)//突变情况
                {
                    $res[$t]['type2']='sharp';
                    for($y=0;$y<count($arrs[$t]);$y++)
                    {
                        $arr2[$t][] = $arrs[$t][$y]['value'];
                        if((max($arr2[$t])>$sharpUp3&&max($arr2[$t])<=$sharpUp2)||(min($arr2[$t])<$sharpLow3&&min($arr2[$t])>=$sharpLow2))
                        {
                            $res[$t]['level2']=3;
                            $result[$t]['type']=$res[$t]['type2'];
                            $result[$t]['level']=$res[$t]['level2']; 
                        }
                        if((max($arr2[$t])>$sharpUp2&&max($arr2[$t])<=$sharpUp1)||(min($arr2[$t])<$sharpLow2&&min($arr2[$t])>=$sharpLow1))
                        {
                            $res[$t]['level2']=2;
                            $result[$t]['type']=$res[$t]['type2'];
                            $result[$t]['level']=$res[$t]['level2'];
                        } 
                        if(max($arr2[$t])>$sharpUp1||min($arr2[$t])<$sharpLow1)
                        {
                            $res[$t]['level2']=1;
                            $result[$t]['type']=$res[$t]['type2'];
                            $result[$t]['level']=$res[$t]['level2'];
                        } 
                    }
                    for($y=0;$y<count($arrs[$t]);$y++)
                    {
                        $arr2[$t][] = $arrs[$t][$y]['value'];
                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                        {
                            $res[$t]['type1']='overRun';
                            $res[$t]['level1']=3;
                            $res[$t]['upLimit']=$overRunUp3;
                            $res[$t]['lowLimit']=$overRunLow3;
                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type2'];
                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level2'];                             
                        }
                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                        {
                            $res[$t]['type1']='overRun';
                            $res[$t]['level1']=2;
                            $res[$t]['upLimit']=$overRunUp2;
                            $res[$t]['lowLimit']=$overRunLow2;
                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type2'];
                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level2']; 
                        } 
                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                        {
                            $res[$t]['type1']='overRun';
                            $res[$t]['level1']=1;
                            $res[$t]['upLimit']=$overRunUp1;
                            $res[$t]['lowLimit']=$overRunLow1;
                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type2'];
                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level2']; 
                        } 
                    }
                    
                    $result[$t]['startTime']=$arrs[$t][0]['tm']+$startTime; 
                    $result[$t]['endTime']=$arrs[$t][0]['tm']+$startTime+count($arrs[$t])-1;
                    $result[$t]['timeLength']=count($arrs[$t]);
                }           

                if($unualTime[$t]>$overRunTime)//超限情况
                {  
                    for($y=0;$y<count($arrs[$t]);$y++)
                    {
                        $arr2[$t][] = $arrs[$t][$y]['value'];
                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                        {
                            $res[$t]['type1']='overRun';
                            $res[$t]['level1']=3;
                            $res[$t]['upLimit']=$overRunUp3;
                            $res[$t]['lowLimit']=$overRunLow3;
                            $result[$t]['type']=$res[$t]['type1'];
                            $result[$t]['level']=$res[$t]['level1']; 
                        }
                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                        {
                            $res[$t]['type1']='overRun';
                            $res[$t]['level1']=2;
                            $res[$t]['upLimit']=$overRunUp2;
                            $res[$t]['lowLimit']=$overRunLow2;
                            $result[$t]['type']=$res[$t]['type1'];
                            $result[$t]['level']=$res[$t]['level1'];  
                        } 
                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                        {
                            $res[$t]['type1']='overRun';
                            $res[$t]['level1']=1;
                            $res[$t]['upLimit']=$overRunUp1;
                            $res[$t]['lowLimit']=$overRunLow1;
                            $result[$t]['type']=$res[$t]['type1'];
                            $result[$t]['level']=$res[$t]['level1'];  
                        } 
                    }
                    $result[$t]['startTime']=$arrs[$t][0]['tm']+$startTime;
                    $result[$t]['endTime']=$arrs[$t][0]['tm']+$startTime+count($arrs[$t])-1;
                    $result[$t]['timeLength']=count($arrs[$t]); 
                }
                if($unualTime[$t]>=$trendTime)//趋势情况
                {
                    for($y=0;$y<count($arrs[$t]);$y++)
                    {
                        if(($y+1)<count($arrs[$t]))
                        {
                            $arr3[$t][] = $arrs[$t][$y]['value'];
                            $gra1[$t][] = $arrs[$t][($y+1)]['value']-$arrs[$t][$y]['value'];
                        }
                    }
                    // dump($gra1[$t]);

                    for($z=0;$z<count($gra1[$t]);$z++)
                    {
                        if(($z+1)<count($gra1[$t]))
                        {
                            if($gra1[$t][$z]>=0&&$gra1[$t][($z+1)]>=0)
                            {
                                $res[$t]['type3']='upTrend';//上升趋势
                                for($y=0;$y<count($arrs[$t]);$y++)
                                {
                                    $arr2[$t][] = $arrs[$t][$y]['value'];
                                    if((max($arr2[$t])-min($arr2[$t]))>$trendUp3)
                                    {
                                        if(max($arr2[$t])<=$overRunUp3&&min($arr2[$t])>=$overRunLow3)
                                        {
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level3']; 
                                        }
                                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=3;
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];                                            
                                        }
                                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=2;
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=1;
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                    }
                                    if((max($arr2[$t])-min($arr2[$t]))>$trendUp2)
                                    {
                                        if(max($arr2[$t])<=$overRunUp3&&min($arr2[$t])>=$overRunLow3)
                                        {
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level3']; 
                                        }
                                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=3;
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];                                            
                                        }
                                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=2;
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=1;
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                    }
                                    if((max($arr2[$t])-min($arr2[$t]))>$trendUp1)
                                    {
                                        if(max($arr2[$t])<=$overRunUp3&&min($arr2[$t])>=$overRunLow3)
                                        {
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level3']; 
                                        }
                                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=3;
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];                                            
                                        }
                                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=2;
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=1;
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                    }
                                    // if((max($arr2[$t])-min($arr2[$t]))>$trendUp2)
                                    // {
                                    //     $res[$t]['level3']=2;
                                    //     $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                    //     $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];
                                    // } 
                                    // if((max($arr2[$t])-min($arr2[$t]))>$trendUp1)
                                    // {
                                    //     $res[$t]['level3']=1;
                                    //     $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                    //     $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];
                                    //     // $result[$t]['scope']=atan((max($arr2[$t])-min($arr2[$t])));
                                    // } 
                                }
                                reset($gra1[$t]);
                                arsort($gra1[$t]);
                                $keyOfMax = key($gra1[$t]);
                                $result[$t]['maxTrendTime']=$arrs[$t][0]['tm']+$startTime+$keyOfMax;
                            }
                            if($gra1[$t][$z]<=0&&$gra1[$t][($z+1)]<=0)
                            {
                                $res[$t]['type3']='downTrend';//下降趋势
                                for($y=0;$y<count($arrs[$t]);$y++)
                                {
                                    $arr2[$t][] = $arrs[$t][$y]['value'];
                                    if((min($arr2[$t])-max($arr2[$t]))<$trendLow3)
                                    {
                                        if(max($arr2[$t])<=$overRunUp3&&min($arr2[$t])>=$overRunLow3)
                                        {
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level3']; 
                                        }
                                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=3;
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];                                            
                                        }
                                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=2;
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=1;
                                            $res[$t]['level3']=3;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                    }
                                    if((min($arr2[$t])-max($arr2[$t]))<$trendLow2)
                                    {
                                        if(max($arr2[$t])<=$overRunUp3&&min($arr2[$t])>=$overRunLow3)
                                        {
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level3']; 
                                        }
                                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=3;
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];                                            
                                        }
                                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=2;
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=1;
                                            $res[$t]['level3']=2;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                    }
                                    if((min($arr2[$t])-max($arr2[$t]))<$trendLow1)
                                    {
                                        if(max($arr2[$t])<=$overRunUp3&&min($arr2[$t])>=$overRunLow3)
                                        {
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level3']; 
                                        }
                                        if((max($arr2[$t])>$overRunUp3&&max($arr2[$t])<=$overRunUp2)||(min($arr2[$t])<$overRunLow3&&min($arr2[$t])>=$overRunLow2))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=3;
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3'];                                            
                                        }
                                        if((max($arr2[$t])>$overRunUp2&&max($arr2[$t])<=$overRunUp1)||(min($arr2[$t])<$overRunLow2&&min($arr2[$t])>=$overRunLow1))
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=2;
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                        if(max($arr2[$t])>$overRunUp1||min($arr2[$t])<$overRunLow1)
                                        {
                                            $res[$t]['type1']='overRun';
                                            $res[$t]['level1']=1;
                                            $res[$t]['level3']=1;
                                            $result[$t]['type']=$res[$t]['type1'].','.$res[$t]['type3'];
                                            $result[$t]['level']=$res[$t]['level1'].','.$res[$t]['level3']; 
                                        }
                                    }
                                }
                                reset($gra1[$t]);
                                arsort($gra1[$t]);
                                $keyOfMax = key($gra1[$t]);
                                $result[$t]['maxTrendTime']=$arrs[$t][0]['tm']+$startTime+$keyOfMax;
                            }
                            if(($gra1[$t][$z]<0&&$gra1[$t][($z+1)]>0)||($gra1[$t][$z]>0&&$gra1[$t][($z+1)]<0))
                            {
                                $res[$t]['type3']='wave';
                                $result[$t]['type']=$res[$t]['type3'];     //波动                       
                            }
                        }
                    }
                }
            }

        }


        
        for($t=0;$t<count($unknownTime);$t++)
        {
            for($y=0;$y<count($nulls[$t]);$y++)
            {
                $resultNull[$t]['type']='unknown';//未知情况
                $resultNull[$t]['startTime']=$nulls[$t][0]['tm']+$startTime; 
                $resultNull[$t]['endTime']=$nulls[$t][0]['tm']+$startTime+count($nulls[$t])-1;
                $resultNull[$t]['timeLength']=count($nulls[$t]);
            }
            array_push($result,$resultNull[$t]);
        }
        // return json($result);
        
        //构造array_column方法
        if (!function_exists('array_column')) {
            function array_column($arr2, $column_key) {
                $data = [];
                foreach ($arr2 as $key => $value) {
                    $data[] = $value[$column_key];
                }
                return $data;
            }
        }
         
        $result_arr = array_column($result, 'startTime');//PHP5.5版本后才出现的函数方法，5.5之前需重新定义
        array_multisort($result_arr, SORT_ASC, $result);
        // return json($result_arr);

 
    return json([
        'msg'=>'ok',
        'code'=>200,
        'dataComment'=>"type:异常类型(unknown:未知,overRun:超限,sharp:突变,wave:波动,trend:趋势),level:等级(1为最高，3为最低),maxTrendTime:趋势或波动最大幅度时刻",
                       "1级超限",
        'data'=>$result]);    
    }
}