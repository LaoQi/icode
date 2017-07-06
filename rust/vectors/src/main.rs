fn main() {
    let v = vec![1,2,3,4,5];
    // ownership
    for i in &v {
        println!("v is {}", i);
    }

    let x: i32;
    x = match v.get(5) {
        Some(i) => *i,
        None => 0,
    };
    println!("x is {}", x);
}
