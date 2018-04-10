<?php

namespace App\Cryosoft;

use App\Models\Study;
use App\Models\StudyEquipment;
use App\Models\Product;
use App\Models\Translation;
use App\Models\ProductElmt;
use App\Models\MeshPosition;
use App\Models\InitialTemperature;

class ProductService
{
    

    public function __construct(\Laravel\Lumen\Application $app)
    {
        $this->app = $app;
        $this->auth = $app['Illuminate\\Contracts\\Auth\\Factory'];
        $this->values = app('App\\Cryosoft\\ValueListService');
        $this->units = app('App\\Cryosoft\\UnitsConverterService');
        $this->studies = app('App\\Cryosoft\\StudyService');
        $this->stdeqp = app('App\\Cryosoft\\StudyEquipmentService');
    }

    public function getAllCompFamily()
    {
        $translations = Translation::where('TRANS_TYPE', 14)
            ->where('CODE_LANGUE', $this->auth->user()->CODE_LANGUE)
            ->get();

        for ($i = 0; $i < $translations->count(); $i++) {
            $translations[$i]->LABEL = \mb_convert_encoding($translations[$i]->LABEL, "UTF-8");
        }
        
        return $translations;
    }

    public function getAllSubFamily($compFamily = 0)
    {
        //compFamily this is value return from combobox after select
        $querys = Translation::where('TRANS_TYPE', 16)
            ->where('CODE_LANGUE', $this->auth->user()->CODE_LANGUE);

        if ($compFamily != 0) {
            $querys->where('ID_TRANSLATION', '>=', $compFamily * 100)
                ->where('ID_TRANSLATION', '<', ($compFamily + 1) * 100);
        }

        $translations = $querys->get();

        for ($i = 0; $i < $translations->count(); $i++) {
            $translations[$i]->LABEL = \mb_convert_encoding($translations[$i]->LABEL, "UTF-8");
        }
        
        return $translations;
    }

    public function getWaterPercentList()
    {
        $data = ["0 - 10%", "10 - 20%", "20 - 30%", "30 - 40%", "40 - 50%", "50 - 60%", "60 - 70%", "70 - 80%", "80 - 90%", "90 - 100%"];

        return $data;
    }

    public function getAllStandardComponents($idStudy = 0, $compFamily = 0, $subFamily = 0, $percentWater = 0)
    {
        $querys = Translation::select('Translation.ID_TRANSLATION', 'Translation.LABEL', 'component.ID_USER', 'component.COMP_RELEASE', 'component.COMP_VERSION', 'component.OPEN_BY_OWNER', 'component.ID_COMP', 'ln2user.USERNAM')
        ->join('component', 'Translation.ID_TRANSLATION', '=', 'component.ID_COMP')
        ->join('ln2user', 'component.ID_USER', '=', 'ln2user.ID_USER')
        ->where('Translation.TRANS_TYPE', 1)
        ->where('Translation.CODE_LANGUE', $this->auth->user()->CODE_LANGUE);
        

        if ($idStudy != 0) {
            $querys->where(function ($query) use ($idStudy) {
                $query->where('component.COMP_IMP_ID_STUDY', 0)
                    ->orWhere('component.COMP_IMP_ID_STUDY', $idStudy);
            })->where(function($query) {
                $query->where('component.COMP_RELEASE', 3)
                    ->orWhere('component.COMP_RELEASE', 4)
                    ->orWhere('component.COMP_RELEASE', 8)
                    ->orWhere(function($q){
                        $q->where('component.COMP_RELEASE', 2)
                        ->where('component.ID_USER', $this->auth->user()->ID_USER);
                    });
            });  
        } else {
            $querys->where('component.COMP_RELEASE', '<>', 6);   
        }

        if ($compFamily > 0) {
            $querys->where('component.CLASS_TYPE', $compFamily); 
        }

        if ($subFamily > 0) {
            $querys->where('component.SUB_FAMILY', $subFamily); 
        }

        if ($percentWater > 0) {
            $querys->where('component.WATER', '>=', ($percentWater - 1) * 10); 
            $querys->where('component.WATER', '<=', $percentWater * 10); 
        }

        $querys->orderBy('Translation.LABEL');

        $components = $querys->get();

        $result = [];
        if (count($components) > 0) {
            $i = 0;
            foreach ($components as $cp) {
                if ($cp->COMP_RELEASE != 9 || $cp->ID_USER == $this->auth->user()->ID_USER || $this->auth->user()->USERPRIO <= 1) {
                    $displayName = $cp->LABEL . ' - ' . $cp->COMP_VERSION . ' (Active)';
                    if ($cp->USERNAM != 'KERNEL') {
                        $displayName .= ' - ' . $cp->USERNAM; 
                    }
                    $result[$i]['ID_COMP'] = $cp->ID_COMP; 
                    $result[$i]['displayName'] = trim($displayName);
                    $i++;
                }
            }
        }

        return $result;
    }
    
    public function getAllSleepingComponents($compFamily = 0, $subFamily = 0, $percentWater = 0)
    {
        $querys = Translation::select('Translation.ID_TRANSLATION', 'Translation.LABEL', 'component.COMP_VERSION', 'component.ID_COMP')
        ->join('component', 'Translation.ID_TRANSLATION', '=', 'component.ID_COMP')
        ->where('Translation.CODE_LANGUE', $this->auth->user()->CODE_LANGUE)
        ->where('Translation.TRANS_TYPE', 1)
        ->where('component.COMP_RELEASE', 6);
        

        if ($compFamily > 0) {
            $querys->where('component.CLASS_TYPE', $compFamily); 
        }

        if ($subFamily > 0) {
            $querys->where('component.SUB_FAMILY', $subFamily); 
        }

        if ($percentWater > 0) {
            $querys->where('component.WATER', '>=', ($percentWater - 1) * 10); 
            $querys->where('component.WATER', '<=', $percentWater * 10); 
        }

        $querys->orderBy('Translation.LABEL');

        $components = $querys->get();

        $result = [];
        if (count($components) > 0) {
            $i = 0;
            foreach ($components as $cp) {
                $displayName = $cp->LABEL . ' - ' . $cp->COMP_VERSION;
                $result[$i]['ID_COMP'] = $cp->ID_COMP; 
                $result[$i]['displayName'] = trim($displayName);
                $i++;
            }
        }

        return $result;
    }

    // search mesh order for one elment on an axis
    public function searchNbPtforElmt(ProductElmt &$elmt, /*int*/ $axe = 2)
    {
        $mshPsts = MeshPosition::where('ID_PRODUCT_ELMT', $elmt->ID_PRODUCT_ELMT)->where('MESH_AXIS', $axe)->orderBy('MESH_ORDER')->get();
        $points = [];
        foreach ($mshPsts as $mshPst) {
            $points[] = $mshPst->MESH_ORDER;
        }
        return $points;
    }

    public function make2Dcontour(Study &$study) 
    {
        $idProduction = $study->productions->first()->ID_PRODUCTION;
        $product = $study->products->first();
        //     ArrayList < Integer > listOfElmtId = getProdElmtComeFromParentProduct();
        $listOfElmtId = ProductElmt::where('ID_PROD', $product->ID_PROD)->where('INSERT_LINE_ORDER', '!=', $study->ID_STUDY)
            ->pluck('ID_PRODUCT_ELMT')->toArray();
        
        // 	// delete old
        //     String sChartPrefix = CONTOUR2D_FILENAME + getUserID() + "_" + idProduction;
        //     deleteCharts(sChartPrefix);

        //     if ((imgType != $this->values->JPG_TYPE)
        //         && (imgType != $this->values->PNG_TYPE)
        //         && (imgType != $this->values->SVG_TYPE))
        //         return null;

        if (!count($listOfElmtId)>0) {
            return null;
        }

        // /*int*/ $ldAxe[] = $this->getPlanFor2DContour(productBean . idShapeencours, $listOfElmtId, $idProduction);
        /*int*/ $ldAxe[] = $this->getPlanFor2DContour($product, $listOfElmtId, $idProduction);
        if (($ldAxe[0] < $this->values->MESH_AXIS_1) || ($ldAxe[1] < $this->values->MESH_AXIS_1)) {
            return null;
        }

        /*double*/ $lfPasTemp = 0;
        /*double[]*/ $BorneTemp = $this->getTemperatureBorne($listOfElmtId, $idProduction);
        $BorneTemp[$this->values->ID_TMIN] = $this->units->prodTemperature($BorneTemp[$this->values->ID_TMIN]);
        $BorneTemp[$this->values->ID_TMAX] = $this->units->prodTemperature($BorneTemp[$this->values->ID_TMAX]);

        /*double[]*/ $res = $this->calculatePasTemp($BorneTemp[$this->values->ID_TMIN], $BorneTemp[$this->values->ID_TMAX]);
        $BorneTemp[$this->values->ID_TMIN] = $res[$this->values->ID_TMIN];
        $BorneTemp[$this->values->ID_TMAX] = res[$this->values->ID_TMAX];
        $lfPasTemp = $res[$this->values->ID_PAS];

        /*double*/ $zStep = $lfPasTemp;
        /*double*/ $zStart = $BorneTemp[$this->values->ID_TMIN];
        /*double*/ $zEnd = $BorneTemp[$this->values->ID_TMAX];

        //     Grid myGrid = getGrideByPlan(listOfElmtId, idProduction, ldAxe[0], ldAxe[1], ldAxe[2]);
        //     if (myGrid == null || myGrid . getNbColumn() == 0 || myGrid . getNbLine() == 0) {
        //         return null;
        //     }

        //     HorizontalNumberAxis axisX = new HorizontalNumberAxis(this . getLabel("OUT_2D_DIM")
        //         + " " + ldAxe[0]
        //         + " (" + convert . prodchartDimensionSymbol() + ")");
        //     axisX . setLabelPosition(HorizontalNumberAxis . LABEL_POSITION_HORIZONTAL_RIGHT_CENTER);
        //     axisX . disableArrow();
        //     axisX . setIntermediaryGapIndicatorVisible(true);

        //     VerticalNumberAxis axisY = new VerticalNumberAxis(this . getLabel("OUT_2D_DIM")
        //         + " " + ldAxe[1]
        //         + " (" + convert . prodchartDimensionSymbol() + ")");
        //     axisY . setLabelPosition(VerticalNumberAxis . LABEL_POSITION_VERTICAL_LEFT_CENTER);
        //     axisY . disableArrow();
        //     axisY . setIntermediaryGapIndicatorVisible(true);

        //     int imageHeight = $this->values->IMG2D_HEIGHT;;
        //     int imageWidth = $this->values->IMG2D_WIDTH;

        //     Contour2D coutour2d = new Contour2D(
        //         imageWidth,
        //         imageHeight,
        //         axisX,
        //         axisY,
        //         myGrid,
        //         zStart,
        //         zEnd,
        //         zStep
        //     );

        //     String sFileName = Ln2Servlet . GEN_IMG_DIR + $this->values->FILE_SEPARATOR
        //         + sChartPrefix;
        //     try {
        // 		//	couleur de fond
        //         coutour2d . setGraphicBackgroundColor($this->values->GRAPHIC_BACKGROUND);
        //         coutour2d . setImageBackgroundColor($this->values->IMG_BACKGROUND);
        //         coutour2d . setBackgroundColorVisible(true);
        //         if (imgType == $this->values->JPG_TYPE) {
        //             sFileName += $this->values->JPG_EXTENSION;
        //             File fimg = new File(Ln2Servlet . getWebAppPath() + $this->values->FILE_SEPARATOR + sFileName);
        //             FileOutputStream fos = new FileOutputStream(fimg);
        //             coutour2d . drawJPEG(fos);
        //             fos . flush();
        //             fos . close();
        //         } else if (imgType == $this->values->PNG_TYPE) {
        //             sFileName += $this->values->PNG_EXTENSION;
        //             File fimg = new File(Ln2Servlet . getWebAppPath() + $this->values->FILE_SEPARATOR + sFileName);
        //             FileOutputStream fos = new FileOutputStream(fimg);
        //             coutour2d . drawPNG(fos);
        //             fos . flush();
        //             fos . close();
        //         } else // if( imgType == ValuesList.SVG_TYPE )  
        //         {
        //             sFileName += $this->values->SVG_EXTENSION;
        //             File fimg = new File(Ln2Servlet . getWebAppPath() + $this->values->FILE_SEPARATOR + sFileName);
        //             FileOutputStream fos = new FileOutputStream(fimg);
        //             coutour2d . drawSVG(fos);
        //             fos . flush();
        //             fos . close();
        //         }

        //     } catch (Exception ex) {
        //         log . error("Error generating an image", ex);
        //         return null;
        //     }
        //     return sFileName;
    }

    public function CheckInitialTemperature(\App\Models\Product &$product) {
        // @TODO: implement
        return true;
    }

    public function DeleteOldInitTemp(\App\Models\Product &$product) {
        // @TODO: implement
        // delete all current initial temperature
        InitialTemperature::where('ID_PRODUCTION', $product->study->ID_PRODUCTION)->delete();
    }

    public function saveMatrixTempComeFromParent(\App\Models\Product &$product)
    {
        echo "start save matrix from parent\n";
        
        /*boolean*/ $bret = false;
        //	save matrix temperature issue from parent study
        $study = $product->study;
        $production = $study->productions->first();
        // try {
            if ($this->studies->isStudyHasParent($study)
                // && IsMeshPositionCalculate()
                // && !IsThereSomeInitialTemperature()
            ) {
                echo "study has parent\n";
                
                $productElmt = null;
                // loop on all product element (from the first inserted to the last excepted for breaded)
                if ($product->productElmts->first()->ID_SHAPE != $this->values->PARALLELEPIPED_BREADED) {
                    /*ProductElmt */$productElmts = \App\Models\ProductElmt::where('ID_PROD', $product->ID_PROD)->orderBy('SHAPE_POS2')->get();
                    // for (int i = vProductElmtBean . size() - 1; i >= 0; i --) {
                    //     productElmt = ((ProductElmtBean) vProductElmtBean . get(i)) . getProductElmt ();
                    //     if (productElmt . getInsertLineOrder() != $this->studies->getSelectedStudy()) {
                    //         break;
                    //     }
                    // }//for
                    foreach ($productElmts as $pElmt) {
                        if ($pElmt->INSERT_LINE_ORDER != $study->ID_STUDY){
                            $productElmt = $pElmt;
                            break;
                        }                        
                    }
                } else {
                    /*ProductElmt */ $productElmts = \App\Models\ProductElmt::where('ID_PROD', $product->ID_PROD)->orderBy('SHAPE_POS2', 'DESC')->get();
                    // for (int i = 0; i < vProductElmtBean . size(); i ++) {
                    //     productElmt = ((ProductElmtBean) vProductElmtBean . get(i)) . getProductElmt ();
                    //     if (productElmt . getInsertLineOrder() != $this->studies->getSelectedStudy()) {
                    //         break;
                    //     }
                    // }//for
                    foreach ($productElmts as $pElmt) {
                        if ($pElmt->INSERT_LINE_ORDER != $study->ID_STUDY) {
                            $productElmt = $pElmt;
                            break;
                        }
                    }
                }
                echo $productElmt == null?"cannot found productElmt\n":"found\n";
                
                if ($productElmt != null) {
                    // search the list of mesh points on axis 2 for this product element
                    /*int */$offset = [];
                    $offset[0] = 0;
                    $offset[1] = 0;
                    $offset[2] = 0;
                    /*ArrayList < Short > */$meshPoint = null;

                    switch ($productElmt->ID_SHAPE) {
                        case $this->values->SLAB:
                        case $this->values->PARALLELEPIPED_STANDING:
                        case $this->values->PARALLELEPIPED_LAYING:
                        case $this->values->CYLINDER_STANDING:
                        case $this->values->CYLINDER_LAYING:
                            $meshPoint = $this->searchNbPtforElmt($productElmt, $this->values->MESH_AXIS_2);
                            $offset[1] = $meshPoint[0];
                            $offset[0] = $offset[2] = 0;
                            break;

                        case $this->values->PARALLELEPIPED_BREADED:
                            $meshPoint = $this->searchNbPtforElmt($productElmt, $this->values->MESH_AXIS_1);
                            $offset[0] = $meshPoint[0];
                            $meshPoint = $this->searchNbPtforElmt($productElmt, $this->values->MESH_AXIS_2);
                            $offset[1] = $meshPoint[0];
                            $meshPoint = $this->searchNbPtforElmt($productElmt, $this->values->MESH_AXIS_3);
                            $offset[2] = $meshPoint[0];
                            break;

                        case $this->values->CYLINDER_CONCENTRIC_STANDING:
                        case $this->values->CYLINDER_CONCENTRIC_LAYING:
                        case $this->values->SPHERE:
                            $offset[0] = $offset[1] = $offset[2] = 0;
                            break;
                    }
                    
                    $parentStudy = Study::findOrFail($study->PARENT_ID);
                    /*StudyEquipments */$sequip = StudyEquipment::findOrFail($study->PARENT_STUD_EQP_ID);
                    /*Product */$parentProduct = $parentStudy->products()->first();
                    echo $sequip != null ? "found parent stdeqp\n" : "not found stdeqp\n";
                    echo $parentProduct != null ? "found parent\n" : "not found parent\n";
                    if (($sequip != null) && ($parentProduct != null)) {
                        // log . debug("search source for save temperature.....");
                        /*boolean */$bNum = ($sequip->BRAIN_TYPE == $this->values->BRAIN_RUN_FULL_YES) ? true : false;
                        /*boolean */$bAna;
                        if ($study->CALCULATION_MODE == ($this->values->STUDY_ESTIMATION_MODE)) {
                            // estimation
                            $bAna = $this->stdeqp->isAnalogicResults($sequip);
                        } else {
                            // optimum or selected
                            $bAna = ($sequip->BRAIN_TYPE != $this->values->BRAIN_RUN_NONE) ? true : false;
                        }

                        if ($bNum) {
                            // log . debug(".....from numerical results");
                            $this->stdeqp->setInitialTempFromNumericalResults(
                                $sequip,
                                $productElmt->ID_SHAPE,
                                $parentProduct,
                                $production
                            );
                        } else if ($bAna) {
                            if ($study->CALCULATION_MODE == ($this->values->STUDY_ESTIMATION_MODE)) {
                                // log . debug(".....from analogic results (estimation)");
                                $this->stdeqp->setInitialTempFromAnalogicalResults(
                                    $sequip,
                                    $productElmt->ID_SHAPE,
                                    $parentProduct,
                                    $production
                                );
                            } else {
                                // log . debug(".....from analogic results (optimum/selected)");
                                $this->stdeqp->setInitialTempFromSimpleNumericalResults(
                                    $sequip,
                                    $productElmt->ID_SHAPE,
                                    $parentProduct,
                                    $production
                                );
                            }
                        }
                        $bret = true;
                    } else {
                        // log . error("Parent study equipments are not exist - may be deleted");
                        throw new \Exception("Parent study equipments are not exist - may be deleted");
                    }
                }
            }
        // } catch (\Exception $qe) {
        //     // log . warn("Exception while saving Temperature", qe);
        //     throw new \Exception("Exception while saving Temperature");
        // }

        return $bret;
    }

    public function PropagationTempElmt (\App\Models\Product &$product, $X, $valueY, $Z, $stemp)
    {
        $study = $product->study;
        /* MODIF: ETUDE SANS CHAINING OU SANS ETUDES FILLES:
         * 	pour que l'enregistrement des températures initiales soit un
         * 	peu plus rapide, étant donné qu'aujourd'hui l'IHM ne permet de saisir
         * 	les températures que suivant l'axe2, l'enregistrement des températures
         * 	dans la base se fera aussi que suivant l'axe 2. Le kernel se chargera 
         * 	de la propagation des températures sur les autres axes => kernel + rapide
         * ETUDE AVEC ENFANT: enregistrement de la matrice 3D
         */

        $lfTemp = floatval($this->units->prodTemperature($stemp));

        // short i, k;
        $i = $k = 0;

        // list < InitialTemperature > listTemp = new ArrayList < InitialTemperature > ();
        $listTemp = [];
        // InitialTemperature temp = null;

        for ($i = 0; $i < $X; $i ++) {
            for ($k = 0; $k < $Z; $k ++) {
                // save node temperature
                $temp = new InitialTemperature();
                $temp->ID_PRODUCTION = ($study->ID_PRODUCTION);
                $temp->MESH_1_ORDER = ($i);
                $temp->MESH_2_ORDER = $valueY;
                $temp->MESH_3_ORDER = ($k);
                $temp->INITIAL_T = ($lfTemp);
                
                // add in initial list
                // $listTemp . add(temp);
                array_push($listTemp, $temp->toArray());
            } // for axis 2
        } // for axis 1
        $slices = array_chunk($listTemp, 100);
        foreach ($slices as $slice) {
            InitialTemperature::insert($slice);
        }
        // save temperature inDB 
        // DBInitialTemperature . insertList(listTemp);
    }
}