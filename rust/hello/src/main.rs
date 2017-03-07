fn main() {
    let (a, b) = (1, 2);
    println!("Hello, world!");
    print_number(a);
    print_number(b);
    sum(a, b);
    print_number(inc(a));
    let f = print_number;
    f(inc(b));
    let c = true;
    println!("c is {}", c);
    let d = '屌';
    println!("d is {}", d);
    let arr = [0; 20];
    let mut m = [1,2,3];
    println!("{}", m.len());
    f(arr[3]);
    let middle = &arr[1..10];
    println!("{}", middle.len());
    let i:fn(i32)-> i32 = inc;
    f(i(41));
}

fn print_number(x: i32){
    println!("x is {}", x);
}

fn sum(x: i32, y:i32) {
    println!("sum is {}", x+y);
}

fn inc(x: i32) -> i32 {
    x + 1
}
