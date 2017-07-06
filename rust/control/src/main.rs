use std::thread;
use std::time::Duration;

fn main() {
    let x = 5;
    if x == 4 {
        println!("Hello, world!");
    } else if 1==1 {
        println!("1== 1");
    }

    let y = if x == 5 { x } else { 10 };
    println!("{}", y);
    let mut x = 0;
    loop {
        println!("loop {}", x);
        //thread::sleep(Duration::from_millis(1000));
        if x > 5 {
            break;
        }
        x += 1;
    }

    let mut done = false;

    while !done {
        x += 1;
        println!("while {}", x);
        if x > 5 {
            break;
        }
        if x > 10 {
            done = true;
        }
    }

    for i in 0..5 {
        println!("i is {}", i);
    }

    for (i, v) in (100..105).enumerate() {
        println!("i = {} and v = {}", i, v);
    }

    let lines = "hello\nhi\nworld".lines();
    for l in lines {
        println!("{}", l);
    }

    'loop1: for x in 0..5 {
        'loop2: for y in 5..10 {
            println!("x is {} and y is {}", x, y);
            if y == 5 {
                continue 'loop1;
            }
        }
    }



}
