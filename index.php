<!DOCTYPE html>
<html>
<head>
    <title>Farewell</title>
    <meta charset="utf-8">
    <link rel="stylesheet" type="text/css" href="css/bootstrap.min.css">
    <style type="text/css">
    body {
        display: -ms-flexbox;
        display: flex;
        -ms-flex-align: center;
        align-items: center;
        padding-top: 40px;
        padding-bottom: 40px;
        background: #fff;
    }

    .main {
        width: 100%;
        max-width: 900px;
        padding: 15px;
        margin: auto;
    }
    </style>
</head>

<body>
    <div class="container main" id="container">
        <div class="row">
            <div class="col-md-12">
                <?php 
                /**
                 * Farewell, PKU Helper
                 */
                require_once("vendor/autoload.php");
                use Illuminate\Database\Capsule\Manager as DB;
                $capsule = new DB;

                $capsule->addConnection([
                    'driver'    => 'mysql',
                    // snipped
                ]);

                $capsule->setAsGlobal();

                function curl_get_contents($url) {
                    $curl = curl_init($url);
                    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($curl, CURLOPT_USERAGENT, "Farewell/1.0");
                    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($curl, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
                    $resp = curl_exec($curl);
                    if(!$resp) { 
                        curl_close($curl); 
                        return null; 
                    }
                    else {
                        curl_close($curl);
                        return json_decode($resp, true);
                    }
                }

                function exportHole($pid, $user_token) {
                    // get hole basic information (check if it exists)
                    // https://pkuhelper.pku.edu.cn/services/pkuhole/api.php?action=getcomment&pid=1678461&PKUHelperAPI=3.0&jsapiver=farewell&user_token=user_token

                    // getone: text
                    $getone = curl_get_contents("https://pkuhelper.pku.edu.cn/services/pkuhole/api.php?action=getone&pid={$pid}&PKUHelperAPI=3.0&jsapiver=farewell&user_token={$user_token}");

                    if(!$getone || !isset($getone["data"]["text"])) {
                        return '';
                    }

                    $returnData = "";
                    $returnData .= "--------------------------------------------------------------\n";
                    $returnData .= "树洞#{$pid}, " . date("Y-m-d H:i:s", $getone["data"]["timestamp"]) . " \n";
                    $returnData .= "--------------------------------------------------------------\n";
                    $returnData .= $getone["data"]["text"];
                    $returnData .= "\n";
                    $returnData .= "--------------------------------------------------------------\n";

                    $getcomment = curl_get_contents("https://pkuhelper.pku.edu.cn/services/pkuhole/api.php?action=getcomment&pid={$pid}&PKUHelperAPI=3.0&jsapiver=farewell&user_token={$user_token}");
                    // return up to 15 comments
                    if(!$getcomment || !isset($getcomment["data"])) {
                        return $returnData;
                    }

                    foreach($getcomment["data"] as $k => $v) {
                        $returnData .= "[#{$v['cid']}] " . date("Y-m-d H:i:s", $v["timestamp"]) . "\n";
                        $returnData .= $v['text'];
                        $returnData .= "\n";

                        if($k > 25) break;
                    }

                    $returnData .= "--------------------------------------------------------------\n";
                    return $returnData;
                }

                if(isset($_POST["uid"], $_POST["user_token"])) {

                    // decypher user_token...
                    $uData = $capsule->table("users")->where("user_token", $_POST["user_token"])->first();
                    if(!$uData || $uData->uid != $_POST["uid"]) {
                    ?>
                    <div class="alert alert-danger">验证失败. 请确认您输入的user token和学号正确, 否则无法验证身份.</div>
                    <?php
                    }
                    else {
                        // now we have $uData

                        $attns = $capsule->table("pkuhole_attention")->where("uid", $uData->uid)->orderBy("pid", "asc")->get();
                        $attns_list = [];
                        $count = 0;
                        $attnsArrayStr = "";
                        foreach($attns as $k => $v) {
                            if($v->pid == 42065) continue; // dont export this one...
                            $attnsArrayStr .= $v->pid . ", ";
                            $attns_list[] = $v->pid;
                            $count++;
                            if($count > 15) { break; /* export at most 15 */ }
                        }

                        if(file_exists("deleted/" . $uData->uid)) {
                            $exportHolesData = "您的账户注销成功。因为您曾经注销过账户，因此无法再导出关注的树洞。";
                        } else {
                            // save list for export later
                            // export holes into human-readable format, by retrieving at most 25 favorited holes
                            $exportHolesData = "导出关注: {$attnsArrayStr}\n";
                            foreach($attns_list as $pid) {
                                $exportHolesData .= exportHole($pid, $uData->user_token);
                            }

                            // perform deletions...
                            $capsule->table("msg")->where("uid", $uData->uid)->delete();
                            $capsule->table("pkuhole_report")->where("uid", $uData->uid)->delete();
                            $capsule->table("pkuhole_attention")->where("uid", $uData->uid)->delete();
                            $capsule->table("secondhand_item")->where("ownerid", $uData->uid)->delete();
                            $capsule->table("lost_found_items")->where("poster_uid", $uData->uid)->delete();
                            $capsule->table("androidpushpool")->where("uid", $uData->uid)->delete();
                            $capsule->table("blacklist")->where("uid", $uData->uid)->delete();
                            $capsule->table("users")->where("uid", $uData->uid)->delete();

                            // ... but save this uid to avoid exporting again.
                            file_put_contents("deleted/" . $uData->uid, "");
                        }
                    ?>
                    <h1>See you again.</h1>
                    <hr>
                    <h4>我们为你生成了PKU Helper Memory Archive, 导出了你关注最早的15个树洞</h4>
                    <p>导出只能进行一次. <b>如果你希望回到PKU Helper的世界, 你可以重新登录授权. 请注意, 你的树洞关注历史不会因为重新登录而恢复.</b> 请复制下方的内容妥善保存.</p>
                    <textarea class="form-control" readonly rows="25"><?php echo $exportHolesData; ?></textarea>

                    <br><br>
                    <p>为了您的安全, 请在保存上述内容完毕后, 关闭本标签页. 谢谢同学们对PKU Helper的支持, 我们有缘<b>再见</b>.</p>
                    <?php
                    }
                }
                else {
                ?>
                <h1>后会有期</h1>
                <hr>
                <h4>如果你不再希望使用 PKU Helper, 为了您的个人信息安全, 可以使用本系统注销账户.</h4>
                <p>
                    <strong>注销</strong>你的PKU Helper账户时, 我们会:
                    <ul>
                        <li><b>撤销</b>您在北京大学数据平台对PKU Helper的<b>全部信息授权</b> (课表, 成绩等个人信息)</li>
                        <li><b>删除</b>您在PKU Helper储存的个人基本信息 (姓名、院系、性别)</li>
                        <li><b>删除</b>您在PKU Helper树洞的<b>收藏、举报、信息历史</b>和PKU Helper下的<b>二手市场条目、失物招领条目</b></li>
                    </ul>
                    请注意: <b>您发送的PKU Helper树洞、回复不会被删除. 如果您需要删除, 请先自行举报自己发送的树洞 (您依然会被封禁).</b><br>
                    为了记录封禁信息, 您的学号和封禁记录仍会保留.
                </p>
                <form action="index.php" method="post">
                    <div class="form-group">
                        <label for="uid">学号</label>
                        <input type="text" class="form-control" id="uid" name="uid" placeholder="1234567890" autofocus>
                    </div>
                    <div class="form-group">
                        <label for="user_token">User Token</label>
                        <input type="text" class="form-control" id="user_token" name="user_token" placeholder="6hjksyoaty550shbn4s90hjsoteshtosm6">
                        <small class="form-text text-warning">为了您的安全, 请确认您位于PKU Helper官方域名 <b>pkuhelper.pku.edu.cn</b>.</small>
                        <small class="form-text text-muted"><a href="https://pkuhelper.pku.edu.cn/hole" target="_blank">前往树洞网页版登录, 右上角[账户]选择[复制User Token]获取.</a> 用于验证您的身份, 避免您注销别人的账户.</small>
                    </div>
                    <p>
                        <span class="text-danger">您的<b>树洞收藏、举报、信息历史、二手市场、失物招领</b>将会从数据库删除, 并<b>无法恢复</b>. 如果您冒用他人身份注销账户, PKU Helper有权移交有关部门处理.</span> 
                    </p>
                    <button type="submit" class="btn btn-danger">我确认，注销PKU Helper账户</button>
                </form>
                <?php
                }
                ?>
            </div>
        </div>
    </div>
</body>

</html>