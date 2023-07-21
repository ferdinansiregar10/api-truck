<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
//use DB;

class Send extends Command
{
	
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:truck';

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
		$this->send();
    }
	
	public function send()
    {   
		$rows = DB::connection('mysql')->select("select * from clicktruck_header 
		where status = 0 and retry < 3 and date(created) >= (date(now()) - interval 1 day) 
		order by id asc limit 10");
		//dd($rows);
		foreach ($rows as $index => $row) {
			$uuid = $row->uuid; 
			//$uuid = "7337eb19-5214-4300-ad03-952a8d9acf63"; 
   
			$data[$index] = array(
			"jenis_armada" => $row->jenis_armada,
			"tipe_armada" => $row->tipe_armada,
			"jumlah_armada" => $row->jumlah_armada,
			"id_ff_ppjk" => $row->id_ff_ppjk,
			"booking_date" => $row->booking_date,
			"pod" => $row->pod,
			"pod_lat" => $row->pod_lat,
			"pod_lon" => $row->pod_lon,
			"total_distance" => $row->total_distance,
			"plan_date" => $row->plan_date,
			"trucking_valid_date" => $row->trucking_valid_date,
			"npwp" => $row->npwp,
			"id_platform" => $row->id_platform,
			"originalPlatformBookingID" => $row->originalPlatformBookingID,
			"price" => $row->price,
			"id_search_booking" => $row->id_search_booking
			);
			
			$row_doks = DB::connection('mysql')->select("select document_no,  document_date, document_status, document_name from clicktruck_documents  where uuid = '".$uuid."'");
			
			$dokumen = array();
			foreach ($row_doks as $doks) {
				$dt_dokumen = array(
					"document_no" => $doks->document_no,
					"document_date" => $doks->document_date,
					"document_status" => $doks->document_status,
					"document_name" => $doks->document_name				
				 );
				 array_push($dokumen , $dt_dokumen);
			}
			
			$data[$index]['dokumen'] = $dokumen;
			
			$rows_muatan = DB::connection('mysql')->select("select muatan_no, muatan_size, muatan_type, isFinished  from clicktruck_muatan  where uuid = '".$uuid."'");
			
			$muatan = array();
			foreach ($rows_muatan as $row_muatan) {
				$dt_muatan = array(
					"muatan_no" => $row_muatan->muatan_no,
					"muatan_size" => $row_muatan->muatan_size,
					"muatan_type" => $row_muatan->muatan_type,
					"over_height" => null,
					"over_width" => null,
					"over_length" => null,
					"over_weight" => null,
					"temperatur" => null,
					"dangerous_type" => null,
					"dangerous_material" => null,
					"gate_pass" => null,
					"hpDriver" => null,
					"id_eseal" => null,
					"isFinished" => $row_muatan->isFinished,
					"namaDriver" => null,
					"status" => null,
					"truckPlateNo" => null			
				 );
				 array_push($muatan , $dt_muatan);
			}
			
			$data[$index]['muatan'] = $muatan;
			
			$rows_od = DB::connection('mysql')->select("select urutan, destination, latitude, longitude from clicktruck_other_destination  where uuid = '".$uuid."'");

			$o_dest = array();
			foreach ($rows_od as $od) {
				$odest = array(
					"urutan" => $od->urutan,
					"destination" => $od->destination,
					"latitude" => $od->latitude,
					"longitude" => $od->longitude,	
				 );
				 array_push($o_dest , $odest);
			}
			$data[$index]['other_destination'] = $o_dest;
			
			$json = json_encode($data[0]);//echo $json;die();
			$url = 'https://nlehub.kemenkeu.go.id/nletrucking/v1/Trucking/Booking/final2';
			$ch = curl_init($url);
			 
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array(
				'nle-api-key: 575c8ae3-a185-457a-a6f8-2ce8233ec4ee',
				'Content-Type: application/json',
				'Cookie: Customs_Cookie=!qWI+oxa21MiFJcbhOyZfqXxe9MeBN41D4HOPEh/AXZq58VnBpXX7i6rvWA0Kfl0G0HXJNGb63WWGl3o=; b690d752fca2b57346a2a5b6c5cbc2e3=5aff6e62a611c0c04e77ad127bd7e425'
			));
				 
			$response = curl_exec($ch);
			
			$json_res = json_decode($response, true);			
			
			if(!empty($json_res['status'])){
				if($json_res['status'] == 'Success' ){
				
				 $q_update = DB::connection('mysql')->table('clicktruck_header')->where('uuid',$uuid)->update(['status' => '1', 'sended' => Carbon::now() ]);
				 
				 $status = '200';
				 
				}else{
					$rows_retry = DB::connection('mysql')->select("select retry  from clicktruck_header where uuid = '".$uuid."'");
					
					$retry = $rows_retry[0]->retry + 1;
					
					$q_update = DB::connection('mysql')->table('clicktruck_header')->where('uuid',$uuid)->update(['retry' => $retry ]);
					
					$status = '400';
				
				}
				
				$logs = DB::connection('mysql')->table('logs')->insert(['uuid' => $uuid, 'notes' => $response, 'status' => $status ]);
				
				curl_close($ch);
			}else{
				$rows_retry = DB::connection('mysql')->select("select retry  from clicktruck_header where uuid = '".$uuid."'");
					
				$retry = $rows_retry[0]->retry + 1;
				
				$q_update = DB::connection('mysql')->table('clicktruck_header')->where('uuid',$uuid)->update(['retry' => $retry ]);
				
				$status = '400';
				
				$logs = DB::connection('mysql')->table('logs')->insert(['uuid' => $uuid, 'notes' => $response, 'status' => $status ]);
				
				curl_close($ch);
			}
						
		}
	}

    
 
}