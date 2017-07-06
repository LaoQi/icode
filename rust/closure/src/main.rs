fn factory() -> Box< Fn(i32) -> i32> {
    let num = 5;
    Box::new(move |x| x+num)
}

fn main() {
    let f =  factory();
    let answer = f(1);
    println!("factory {}", answer);

    let v = 32;
    let f = |x:i32| v + x;
    let mut f2 = move|x:i32| v + x;
    for i in 0..5{
        let c = f(i);
        let mut d = f2(i);
        println!("{} {}", c, d);
    }
}
