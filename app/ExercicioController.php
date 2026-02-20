use iluminate\Http\Request;

class ExercicioController extends Controller{

    public function exibirFormulario(){
        retur view('exercicio');
    }

    public function calcularSoma(Request $request){
        $valor1 = $request -> input ('valor1');
        $valor2 = $request -> input ('valor2');
    }

    public function exibirFormulario2(){
        retur view('exercicio2');
    }
    
    public function calcularSub(Request $request){
        $valor1 = $request -> input ('valor1');
        $valor2 = $request -> input ('valor2');
        $sub = 
    }

    


}