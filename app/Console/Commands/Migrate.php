<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
//use DB;

class Migrate extends Command
{
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:truck';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
		parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
		$this->migrate();
    }
	
	public function migrate()
    {   
		//get last id
		$sql_id = DB::connection('mysql')->select("select id_job from clicktruck_header ch order by id_job desc");
		if (!$sql_id) {
		   $id_awal = 10000;
		}else{
			$id_awal = $sql_id[0]->id_job;
		}
		$id_akhir = $id_awal + 500;
		
		//select data
		$rows = DB::connection('mysql2')->select("select distinct cj.id, cj.uuid  from clicktruck_jobs cj 
		left join clicktruck_job_locations cl on cj.uuid = cl.job_uuid 
		where (cj.id between '".$id_awal."' and '".$id_akhir."')  and cj.status in  ('complete', 'completed') and cl.country = 'Indonesia'");
		//dd($rows);
		foreach ($rows as $row) {
			$uuid = $row->uuid;
			//$uuid = "374848b7-6978-4750-b779-214981c34e67";
			$id_job = $row->id;
			//echo $uuid ;
			$rows_header = DB::connection('mysql2')->select("select cj.order_truck_type as jenis_armada, str_to_date(cj.booking_date, '%Y-%m-%d %T') as booking_date,  cj.plan_date as plan_date, cj.expired_time as trucking_valid_date,cj.truck_price as price,cj.uuid as id_search_booking, cj.uuid as originalplatformbookingid, cjc.truck_type
				from clicktruck_jobs cj
				join clicktruck_job_cargos cjc  on cjc.job_uuid = cj.uuid
				where cj.uuid = '".$uuid."' ");
			
			$header = array();
			
			if(empty($rows_header[0]->jenis_armada)){
				$header['jenis_armada'] = 'truck';
			}else{
				$header['jenis_armada'] = $rows_header[0]->jenis_armada;
			}
			
			$rows_type_armada = DB::connection('mysql2')->select("SELECT cjc.truck_type  from 	  clicktruck_job_cargos cjc join clicktruck_jobs cj on cjc.job_uuid = cj.uuid  		where cj.uuid = '".$uuid."' ");
			if(empty($rows_type_armada)){
				$header['tipe_armada'] = 'truck';
			}else{
				$header['tipe_armada'] = $rows_type_armada[0]->truck_type;
			}
			
			$rows_armada = DB::connection('mysql2')->select("select count(cja.uuid) as jumlah_truk
							from clicktruck_job_assigneds cja 
							where cja.job_uuid = '".$uuid."'
							group by cja.job_uuid");
			if(empty($rows_armada)){
				$header['jumlah_armada'] = '1';
			}else{
				$header['jumlah_armada'] = $rows_armada[0]->jumlah_truk;
			}
			
			$rows_id_ff_ppjk = DB::connection('mysql2')->select("select c.tax_number 
					from clicktruck_jobs cj 
					join company_service.companies c on cj.requestor_company_uuid = c.uuid 
					where cj.uuid = '".$uuid."'");
			if(empty($rows_id_ff_ppjk)){
				$header['id_ff_ppjk'] = '1';
			}else{
				$header['id_ff_ppjk'] = $rows_id_ff_ppjk[0]->tax_number;
			}
			
			$rows_pod = DB::connection('mysql2')->select("select cjl.name  from clicktruck_job_locations cjl 
						join clicktruck_jobs cj on cj.uuid = cjl.job_uuid 
						where cjl.type = 'drop_off' and cj.uuid = '".$uuid."' ");
			if(empty($rows_pod)){
				$header['pod'] = '1';
			}else{
				$header['pod'] = $rows_pod[0]->name;
			}
			
			$rows_pod_lat = DB::connection('mysql2')->select('select replace(longlat.pod_lat,"\"", "") as pod_lat from 
				(select cjl.coordinate -> "$.latitude" as pod_lat
				from clicktruck_job_locations cjl 
				join clicktruck_jobs cj on cj.uuid = cjl.job_uuid 
				where cjl.`type` = "drop_off" and cj.uuid = "'.$uuid.'") as longlat ');
			if(empty($rows_pod_lat)){
				$header['pod_lat'] = '1';
			}else{
				$header['pod_lat'] = $rows_pod_lat[0]->pod_lat;
			}
			
			$rows_pod_lon = DB::connection('mysql2')->select('select replace(longlat.pod_lon,"\"", "") as pod_lon from 
				(select cjl.coordinate -> "$.longitude" as pod_lon
				from clicktruck_job_locations cjl 
				join clicktruck_jobs cj on cj.uuid = cjl.job_uuid 
				where cjl.`type` = "drop_off" and cj.uuid = "'.$uuid.'") as longlat ');
			if(empty($rows_pod_lon)){
				$header['pod_lon'] = '1';
			}else{
				$header['pod_lon'] = $rows_pod_lon[0]->pod_lon;
			}
			
			if(empty($rows_header[0]->booking_date)){
				$header['booking_date'] = '2022-01-01 00:00:00';
			}else{
				$header['booking_date'] = $rows_header[0]->booking_date;
			}
			
			if(empty($rows_header[0]->plan_date)){
				$header['plan_date'] = '2022-01-01 00:00:00';
			}else{
				$header['plan_date'] = $rows_header[0]->plan_date;
			}
			
			if(empty($rows_header[0]->trucking_valid_date)){
				$header['trucking_valid_date'] = '2022-01-01 00:00:00';
			}else{
				$header['trucking_valid_date'] = $rows_header[0]->trucking_valid_date;
			}
			
			if(empty($rows_header[0]->price)){
				$header['price'] = '1000000';
			}else{
				$header['price'] = $rows_header[0]->price;
			}
				
			$header['total_distance'] = '0';
			$header['npwp'] = $header['id_ff_ppjk'];
			$header['id_platform'] = 'PL002';
			$header['originalplatformbookingid'] = $uuid;
			$header['id_search_booking'] = $uuid;
			$header['uuid'] = $uuid;
			$header['status'] = '0';
			$header['created'] = Carbon::now();
			$header['id_job'] = $id_job;
			
			//insert clicktruck_header
			$header = DB::connection('mysql')->table('clicktruck_header')->insert($header);
			
			//document
			$rows_document = DB::connection('mysql2')->select("select cj.uuid, cjd.number as document_no, cjd.date as document_date,
					cjd.type as document_name 
					from clicktruck_job_documents cjd
					join clicktruck_jobs cj on cj.uuid = cjd.job_uuid 
					where cj.uuid = '".$uuid."'
					order by cjd.job_uuid ");
			//dd($rows_document[0]);
			if(!empty($rows_document[0]->document_no)){
				$document = DB::connection('mysql')->table('clicktruck_documents')
							->insert(['uuid' => $rows_document[0]->uuid,
									  'document_no' => $rows_document[0]->document_no,
									  'document_date' => $rows_document[0]->document_date,
									  'document_name' => $rows_document[0]->document_name
								    ]);
			}	

			$rows_muatan = DB::connection('mysql2')->select("select concat(cjc.weight, ' ', cjc.size_uom) as muatan_size, cjc.`type` as muatan_type,
					( 	select 1+ count(*) from clicktruck_job_cargos cjc2
						where cjc2.job_uuid = cjc.job_uuid
						and 	
						(cjc2.id > cjc.id)		
					) as muatan_no	
					from clicktruck_job_cargos cjc
					where cjc.job_uuid = '".$uuid."'
					order by job_uuid ,muatan_no");
					
			$rmuatan = array();
			
			$rmuatan["uuid"] = $uuid;
			
			if(empty($rows_muatan[0]->muatan_no)){
				$rmuatan["muatan_no"] = '1';
			}else{
				$rmuatan["muatan_no"] = '1';
			}
			
			if(empty($rows_muatan[0]->muatan_size)){
				$rmuatan["muatan_size"] = 'cbm';
			}else{
				$rmuatan["muatan_size"] = '1';
			}
			
			if(empty($rows_muatan[0]->muatan_type)){
				$rmuatan["muatan_type"] = 'General Cargo';
			}else{
				$rmuatan["muatan_type"] = '1';
			}
			
			$muatan = DB::connection('mysql')->table('clicktruck_muatan')->insert($rmuatan);
			
		}
	}

    
 
}